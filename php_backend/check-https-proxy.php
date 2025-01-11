<?php

require_once __DIR__ . '/../func.php';
require_once __DIR__ . '/../func-proxy.php';

use PhpProxyHunter\Proxy;
use \PhpProxyHunter\ProxyDB;
use \PhpProxyHunter\Server;

global $isCli;

$db = new ProxyDB(__DIR__ . '/../src/database.sqlite');
$userId = getUserId();
$request = parsePostData();
$currentScriptFilename = basename(__FILE__, '.php');

if (!$isCli && isset($request['proxy'])) {
  // Web server
  $proxy = $request['proxy'];
  $hashFilename = "$currentScriptFilename-" . $userId;
  $proxy_file = tmp() . "/proxies/$hashFilename.txt";
  write_file($proxy_file, $proxy);

  // Run a long-running process in the background
  $file = __FILE__;
  $output_file = tmp() . "/logs/$hashFilename.out";
  setMultiPermissions([$file, $output_file], true);
  $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  $cmd = "php " . escapeshellarg($file);
  $cmd .= " --userId=" . escapeshellarg($userId);
  $cmd .= " --file=" . escapeshellarg($proxy_file);
  $cmd = trim($cmd);

  echo $cmd . "\n\n";

  $cmd = sprintf("%s > %s 2>&1", $cmd, escapeshellarg($output_file));
  $runner = tmp() . "/runners/$hashFilename" . ($isWin ? '.bat' : "");

  write_file($runner, $cmd);

  runBashOrBatch($runner); // Re-run the script in the background

  // render index page
  exit(read_file(__DIR__ . '/index.html'));
} else {
  $options = getopt("f::p::", ["file::", "proxy::"]);

  $file = isset($options['f']) ? $options['f'] : (isset($options['file']) ? $options['file'] : null);
  $hashFilename = basename($file, '.txt');
  if ($hashFilename == '.txt' || empty($hashFilename)) {
    $hashFilename = "$currentScriptFilename-cli.txt";
  }
  $proxy = isset($options['p']) ? $options['p'] : (isset($options['proxy']) ? $options['proxy'] : null);

  if (!$file && !$proxy) {
    _log("No proxy file provided. Searching for proxies in database.");

    $proxiesDb = array_merge($db->getWorkingProxies(100), $db->getUntestedProxies(100));
    $filteredArray = array_filter($proxiesDb, function ($item) {
      // Keep dead proxies
      if (strtolower($item['status']) != 'active') return true;
      // Only pick non-SSL proxies
      return strtolower($item['https']) != 'true';
    });
    $proxyArray = array_map(function ($item) {
      return $item['proxy'];
    }, $filteredArray);
    $proxy = json_encode($proxyArray);
  } else if ($file) {
    $read = read_file($file);
    if ($read) $proxy = $read;
  }
}

if (empty($hashFilename)) $hashFilename = "CLI";
$lockFolder = tmp() . '/runners/';
$lockFilePath = $lockFolder . $hashFilename . '.lock';
$lockFiles = glob($lockFolder . "/$currentScriptFilename*.lock");
// Check if the number of lock files exceeds the limit
if (count($lockFiles) > 2) {
  _log("Proxy checker process limit reached: More than 2 instances of '$currentScriptFilename' are running. Terminating process.");
  exit;
}

// Create or open the lock file.
$lockFile = fopen($lockFilePath, 'w+');
if ($lockFile === false) {
  throw new RuntimeException("Failed to open or create the lock file: $lockFilePath");
}

$runAllowed = false;

// Attempt to acquire an exclusive lock to prevent multiple processes from running simultaneously.
if (flock($lockFile, LOCK_EX)) {
  _log("$lockFilePath Lock acquired");
  $runAllowed = true;

  // Perform the critical task:
  // - Clear the log file before starting the operation.
  // - Run the `check` function with the provided proxy.
  truncateFile(get_log_file());
  check($proxy);

  // Release the lock after completing the critical section.
  flock($lockFile, LOCK_UN);
  _log("Lock released");
} else {
  // Log that the lock is held by another process, preventing this one from proceeding.
  _log("Another process is still running");
}

// Close the lock file handle to release system resources.
fclose($lockFile);

if ($runAllowed) {
  // Delete the lock file to clean up after the process has finished.
  delete_path($lockFilePath);
}

function get_log_file()
{
  global $hashFilename;
  $_logFile = tmp() . "/logs/$hashFilename.txt";
  if (!file_exists($_logFile)) {
    file_put_contents($_logFile, "");
  }
  setMultiPermissions([$_logFile], true);
  return $_logFile;
}

/**
 * Logs messages to a file and outputs them to the console.
 *
 * @param mixed ...$args List of arguments to be logged. They are concatenated into a single string.
 * @return void
 */
function _log(...$args): void
{
  $_logFile = get_log_file();
  $message = join(" ", $args) . PHP_EOL;

  append_content_with_lock($_logFile, $message);
  echo $message;
}

/**
 * Check if the proxy is working
 * @param string $proxy proxy string
 */
function check(string $proxy)
{
  global $db, $hashFilename, $currentScriptFilename;
  $proxies = extractProxies($proxy, $db, true);
  shuffle($proxies);

  $count = count($proxies);
  $logFilename = str_replace("$currentScriptFilename-", "", $hashFilename);
  _log(trim("$logFilename Checking $count proxies..."));

  for ($i = 0; $i < $count; $i++) {
    $no = $i + 1;
    $item = $proxies[$i];

    // Skip already SSL-supported proxy
    if ($item->https == 'true' && $item->status == 'active') {
      _log("[$no] Skipping proxy {$item->proxy}: Already supports SSL and is active.");
      continue;
    } else if ($item->last_check) {
      if ($item->status == 'dead') {
        $expired = isDateRFC3339OlderThanHours($item->last_check, 5);
        if ($item->last_check && $expired) {
          _log("[$no] Skipping proxy {$item->proxy}: Marked as dead, but was recently checked at {$item->last_check}.");
          continue;
        }
      } else if ($item->https == 'false' && $item->status != 'untested') {
        $expired = isDateRFC3339OlderThanHours($item->last_check, 5);
        if ($item->last_check && $expired) {
          _log("[$no] Skipping proxy {$item->proxy}: Does not support SSL, but was recently checked at {$item->last_check}.");
          continue;
        }
      }
    }

    $ssl_protocols = [];
    $protocols = ['http', 'socks4', 'socks5'];
    $latencies = [];
    foreach ($protocols as $protocol) {
      $curl = buildCurl($item->proxy, $protocol, 'https://www.ssl.org/', [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0'
      ]);
      $result = curl_exec($curl);
      $msg = "[$no] $protocol://{$item->proxy} ";

      if ($result) {
        $info = curl_getinfo($curl);
        curl_close($curl);
        if ($info['http_code'] == 200) {
          $msg .= round($info['total_time'], 2) . 's ';
          // Get the time taken for the request in milliseconds
          $latencies[] = round($info['total_time'] * 1000, 2);

          if (checkRawHeadersKeywords($result)) {
            $msg .= "SSL dead (Azenv). ";
          } else {
            preg_match("/<title>(.*?)<\/title>/is", $result, $matches);
            if (!empty($matches)) {
              $msg .= "Title: " . $matches[1];
              if (strtolower($matches[1]) == strtolower("SSL Certificate Checker")) {
                $msg .= " (VALID) ";
                $ssl_protocols[] = $protocol;
              } else {
                $msg .= " (INVALID) ";
              }
            } else {
              $msg .= "Title: N/A ";
            }
          }
        }
      } else {
        $msg .= "SSL dead ";
      }

      _log(trim($msg));
    }
    // Prepare the base data array
    $data = ['https' => !empty($ssl_protocols) ? 'true' : 'false'];

    // If ssl_protocols are available, add the corresponding fields
    if (!empty($ssl_protocols)) {
      $data['type'] = join("-", $ssl_protocols);
      $data['status'] = 'active';

      // Add the highest latency if available
      if (!empty($latencies)) {
        $data['latency'] = max($latencies);
      }
    }

    // Perform the database update
    $db->updateData($item->proxy, $data);
  }

  _log("Done checking proxies.");
}
