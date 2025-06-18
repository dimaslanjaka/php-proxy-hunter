<?php

if (!function_exists('tmp')) {
  require_once __DIR__ . '/autoload.php';
  require_once __DIR__ . '/../func-proxy.php';
}

use PhpProxyHunter\BaseController;

class CheckDuplicateProxyController extends BaseController
{
  private $outputFile;

  public function __construct()
  {
    parent::__construct(); // Ensure BaseController's constructor runs
    $this->outputFile = tmp() . '/proxies/' . get_class($this) . '.txt';
  }

  public function indexAction()
  {
    return json_decode(read_file($this->outputFile) ?? '[]', true);
  }

  public function fetchDuplicates(string $ip)
  {
    if (!$this->isCLI) {
      throw new Exception('Only CLI Allowed');
    }
    if (!isValidIp($ip)) {
      $this->log("[CHECK-DUPLICATE] Invalid IP address provided: $ip");
      return;
    }

    $timer = new \PhpProxyHunter\ExecutionTimer(30, 3); // 30s limit, 3s safety buffer
    $lock = new \PhpProxyHunter\FileLockHelper($this->getLockFilePath());
    if ($lock->isLockedByAnotherProcess()) {
      $this->log("[CHECK-DUPLICATE] Another instance is running, exiting.");
      return;
    }

    if ($lock->lock(LOCK_EX)) {
      $this->log("[CHECK-DUPLICATE] Lock acquired, starting check for IP: $ip");
      $db = new \PhpProxyHunter\ProxyDB();
      if ($db->isDatabaseLocked()) {
        $this->log("[CHECK-DUPLICATE] Database is locked, exiting.");
        return;
      }
      /**
       * @var PDO
       */
      $pdo = $db->db->pdo;

      $sql = "SELECT * FROM proxies
      WHERE
          substr(proxy, 1, instr(proxy, ':') - 1) = :ip
      ORDER BY proxy;";

      $firstRowStmt = $pdo->prepare("SELECT id FROM proxies WHERE substr(proxy, 1, instr(proxy, ':') - 1) = :ip ORDER BY id LIMIT 1");
      $firstRowStmt->bindParam(':ip', $ip, PDO::PARAM_STR);
      $firstRowStmt->execute();
      $firstRow = $firstRowStmt->fetch(PDO::FETCH_ASSOC);

      $stmt = $pdo->prepare($sql);
      $stmt->execute(['ip' => $ip]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $open_ports = [];
      $row_map = [];
      foreach ($rows as $row) {
        $row_map[$row['proxy']] = $row;
      }

      foreach ($rows as $row) {
        $timer->exitIfNeeded("Graceful exit: ran out of safe time.");
        if (isValidProxy($row['proxy']) === false) {
          // Delete invalid proxies
          $deleteStmt = $pdo->prepare("DELETE FROM proxies WHERE id = :id");
          $deleteStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
          $deleteStmt->execute();
          $this->log("[CHECK-DUPLICATE] {$row['proxy']} is not a valid proxy format. [DELETED]");
          continue;
        }

        if (!isValidIp($ip)) {
          // Delete invalid IP proxies
          $deleteStmt = $pdo->prepare("DELETE FROM proxies WHERE id = :id");
          $deleteStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
          $deleteStmt->execute();
          $this->log("[CHECK-DUPLICATE] {$row['proxy']} has invalid IP: $ip. [DELETED]");
          continue;
        }

        $this->log("[CHECK-DUPLICATE] Checking proxy: {$row['proxy']} with IP: $ip");

        // Check if the port is open
        if (in_array($row['proxy'], $open_ports)) {
          $this->log("[CHECK-DUPLICATE] Proxy {$row['proxy']} already checked and open, skipping.");
          continue;
        }
        if ($row['status'] === 'active') {
          if (isDateRFC3339OlderThanHours($row['last_check'], 24)) {
            $this->log("[CHECK-DUPLICATE] Proxy {$row['proxy']} is active but last checked over 24 hours ago, rechecking.");
          } else {
            $this->log("[CHECK-DUPLICATE] Proxy {$row['proxy']} is active and recently checked, skipping. [SKIPPED]");
            continue; // Skip if it's active and checked recently
          }
        }
        if (isPortOpen($row['proxy'])) {
          $this->log("[CHECK-DUPLICATE] Proxy {$row['proxy']} is open.");
          $db->updateData($row['proxy'], ['status' => 'untested'], false);
          $open_ports[] = $row['proxy'];
        } else {
          if ($firstRow && $firstRow['id'] == $row['id']) {
            $this->log("[CHECK-DUPLICATE] Skipping deletion of first proxy: {$row['proxy']}. [SKIPPED]");
            continue;
          }
          $deleteStmt = $pdo->prepare("DELETE FROM proxies WHERE id = :id");
          $deleteStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
          $deleteStmt->execute();
          $this->log("[CHECK-DUPLICATE] Proxy {$row['proxy']} deleted due to closed or dead status. [DELETED]");
        }
      }

      // Now, check open ports for working protocols
      foreach ($open_ports as $proxy) {
        $row = $row_map[$proxy];
        $curls = [
          'non-ssl' => [
            'http' => buildCurl($proxy, 'http', 'http://httpforever.com/', [], $row['username'], $row['password'], 'GET', null, 0),
            'socks4' => buildCurl($proxy, 'socks4', 'http://httpforever.com/', [], $row['username'], $row['password'], 'GET', null, 0),
            'socks5' => buildCurl($proxy, 'socks5', 'http://httpforever.com/', [], $row['username'], $row['password'], 'GET', null, 0),
          ],
          'ssl' => [
            'http' => buildCurl($proxy, 'http', 'https://forums.docker.com/', [], $row['username'], $row['password'], 'GET', null, 0),
            'socks4' => buildCurl($proxy, 'socks4', 'https://forums.docker.com/', [], $row['username'], $row['password'], 'GET', null, 0),
            'socks5' => buildCurl($proxy, 'socks5', 'https://forums.docker.com/', [], $row['username'], $row['password'], 'GET', null, 0),
          ],
        ];
        // Apply default cURL options
        $cookieFile = tmp() . '/cookies/default.txt';
        foreach ($curls as $type => $protocols) {
          foreach ($protocols as $protocol => $ch) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);   // Save cookies to file
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);  // Use cookies from file
          }
        }

        $working_proxies = [];
        $working_ssl = false;

        // Check SSL
        foreach ($curls['ssl'] as $protocol => $ch) {
          // Execute the request and get the response
          $response = curl_exec($ch);

          // Check for cURL errors
          if (curl_errno($ch)) {
            $this->log("cURL SSL Error (" . $protocol . "://" . $proxy . "): " . curl_error($ch));
            continue;
          }

          // Get info about the request
          $info = curl_getinfo($ch);

          // Close cURL session
          curl_close($ch);

          if ($info['http_code'] == 200) {
            $latencyMs = round($info['total_time'] * 1000, 2);
            preg_match("/<title>(.*?)<\/title>/is", $response, $titleMatches);
            $title = $titleMatches[1] ?? 'No title found';
            if (trim(strtolower($title)) === strtolower('Docker Community Forums')) {
              if (!isset($working_proxies[$proxy])) {
                $working_proxies[$proxy] = [];
              }
              $working_proxies[$proxy]['protocol'][] = $protocol;
              $working_proxies[$proxy]['latency'][] = $latencyMs;
              // Delete curl item from the array
              unset($curls['ssl'][$protocol]);
              // Delete non-SSL curl item
              unset($curls['non-ssl'][$protocol]);
              // Mark as working SSL
              $working_ssl = true;
              $this->log("[CHECK-DUPLICATE] Proxy $proxy is active and supports SSL. Latency: {$latencyMs}ms, Title: $title");
            }
          }
        }
        // If no SSL working proxies found, check non-SSL
        if (!$working_ssl) {
          // Check non-SSL
          foreach ($curls['non-ssl'] as $protocol => $ch) {
            // Execute the request and get the response
            $response = curl_exec($ch);

            // Check for cURL errors
            if (curl_errno($ch)) {
              $this->log("cURL non-SSL Error (" . $protocol . "://" . $proxy . "): " . curl_error($ch));
              continue;
            }

            // Get info about the request
            $info = curl_getinfo($ch);

            // Close cURL session
            curl_close($ch);

            if ($info['http_code'] == 200) {
              $latencyMs = round($info['total_time'] * 1000, 2);
              preg_match("/<title>(.*?)<\/title>/is", $response, $titleMatches);
              $title = $titleMatches[1] ?? 'No title found';
              if (trim(strtolower($title)) === strtolower('HTTP Forever')) {
                if (!isset($working_proxies[$proxy])) {
                  $working_proxies[$proxy] = [];
                }
                $working_proxies[$proxy]['protocol'][] = $protocol;
                $working_proxies[$proxy]['latency'][] = $latencyMs;
                // Delete curl item from the array
                unset($curls['ssl'][$protocol]);
                $this->log("[CHECK-DUPLICATE] Proxy $proxy is active and supports non-SSL. Latency: {$latencyMs}ms, Title: $title");
              }
            }
          }
        }

        // Check if we have any working proxies
        if (isset($working_proxies[$proxy])) {
          $protocols = implode('-', $working_proxies[$proxy]['protocol']);
          $latencies = implode('ms, ', $working_proxies[$proxy]['latency']) . 'ms';
          $this->log("[CHECK-DUPLICATE] Proxy $proxy is working with protocols: $protocols and latencies: $latencies.");
          // Update the proxy status to active
          $db->updateData($proxy, ['status' => 'active', 'type' => $protocols], false);
        } else {
          // Delete the proxy if it has no working protocols, but only if it was not the first one
          if ($firstRow && $firstRow['id'] == $row['id']) {
            $this->log("[CHECK-DUPLICATE] Skipping deletion of first proxy: {$proxy}. [SKIPPED]");
          } else {
            $deleteStmt = $pdo->prepare("DELETE FROM proxies WHERE id = :id");
            $deleteStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
            $deleteStmt->execute();
            $this->log("[CHECK-DUPLICATE] Proxy $proxy deleted due to no working protocols. [DELETED]");
          }
        }
      }
    }
  }
}

// Only run when executed directly from CLI, not when included or required
if (
  php_sapi_name() === 'cli' &&
  realpath(__FILE__) === realpath($_SERVER['argv'][0] ?? '')
) {
  $options = getopt("", ["ip:"]); // Accepts --ip=<IP> argument
  $ipArg = $options['ip'] ?? null;

  $check = new CheckDuplicateProxyController();

  if (isValidIp($ipArg)) {
    $check->fetchDuplicates($ipArg);
  } else {
    $list = new ListDuplicateProxyController();
    $list->log("No IP provided, fetching duplicates from the database.");
    $data = $list->fetchDuplicates();

    // If $data is a numerically indexed array, convert it to associative array with IP as key
    if (is_array($data) && !empty($data) && isset($data[0]['proxy'])) {
      $assocData = [];
      foreach ($data as $row) {
        $ip = explode(':', $row['proxy'])[0];
        if (!isset($assocData[$ip])) {
          $assocData[$ip] = [];
        }
        $assocData[$ip][] = $row;
      }
      $data = $assocData;
    }
    // Shuffle the IP keys
    if (is_array($data) && !empty($data)) {
      $keys = array_keys($data);
      shuffle($keys);
      $shuffledData = [];
      foreach ($keys as $key) {
        $shuffledData[$key] = $data[$key];
      }
      $data = $shuffledData;
    }

    if (is_array($data) && !empty($data)) {
      $firstKey = array_key_first($data);
      $firstCount = count($data[$firstKey]);
      $list->log("IP: $firstKey $firstCount proxies found.");
      $check->fetchDuplicates($firstKey);
    } else {
      $list->log("No proxies found to check.");
    }
  }
}
