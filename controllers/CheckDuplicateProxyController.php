<?php

if (!function_exists('tmp')) {
  require_once __DIR__ . '/../func.php';
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
    $timer = new \PhpProxyHunter\ExecutionTimer(30, 3); // 30s limit, 3s safety buffer
    $db = new \PhpProxyHunter\ProxyDB();
    /**
     * @var PDO
     */
    $pdo = $db->db->pdo;

    $sql = "
      SELECT * FROM proxies
      WHERE
          substr(proxy, 1, instr(proxy, ':') - 1) = :ip
      ORDER BY proxy;
  ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['ip' => $ip]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
      $timer->exitIfNeeded("Graceful exit: ran out of safe time.");
      ;
      if (isValidProxy($row['proxy']) === false) {
        // Delete invalid proxies
        $deleteStmt = $pdo->prepare("DELETE FROM proxies WHERE id = :id");
        $deleteStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
        $deleteStmt->execute();
        echo "[CHECK-DUPLICATE] {$row['proxy']} is invalid, deleted.\n";
        continue;
      }

      $proxy_ip = strstr($row['proxy'], ':', true);
      if (!isValidIp($proxy_ip)) {
        // Delete invalid IP proxies
        $deleteStmt = $pdo->prepare("DELETE FROM proxies WHERE id = :id");
        $deleteStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
        $deleteStmt->execute();
        echo "[CHECK-DUPLICATE] Invalid IP: $proxy_ip for proxy: {$row['proxy']}\n";
        continue;
      }

      echo "[CHECK-DUPLICATE] Checking proxy: {$row['proxy']} with IP: $proxy_ip\n";

      if (isPortOpen($row['proxy'])) {
        echo "[CHECK-DUPLICATE] Proxy {$row['proxy']} is open.\n";
        $db->updateData($row['proxy'], ['status' => 'untested'], false);
      } else {
        // Fetch the first row ID for this IP to compare
        $firstRowStmt = $pdo->prepare("SELECT id FROM proxies WHERE substr(proxy, 1, instr(proxy, ':') - 1) = :ip ORDER BY id LIMIT 1");
        $firstRowStmt->bindParam(':ip', $proxy_ip, PDO::PARAM_STR);
        $firstRowStmt->execute();
        $firstRow = $firstRowStmt->fetch(PDO::FETCH_ASSOC);

        if (!$firstRow) {
          echo "[CHECK-DUPLICATE] No first row found for IP: $proxy_ip, skipping deletion.\n";
          continue;
        }

        if ($firstRow['id'] === $row['id']) {
          echo "[CHECK-DUPLICATE] Keeping first proxy: {$row['proxy']} as it is the first one. [SKIPPED]\n";
          continue;
        }

        $deleteStmt = $pdo->prepare("DELETE FROM proxies WHERE id = :id");
        $deleteStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
        $deleteStmt->execute();
        echo "[CHECK-DUPLICATE] Proxy {$row['proxy']} deleted due to closed or dead status. [DELETED]\n";
      }
    }
  }
}

// Only run when executed directly from CLI, not when included or required
if (php_sapi_name() === 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
  $list = new ListDuplicateProxyController();
  $data = $list->fetchDuplicates();
  $firstKey = array_key_first($data);
  $firstValue = $data[$firstKey];
  // var_dump($firstKey);
  // var_dump($firstValue);
  $check = new CheckDuplicateProxyController();
  $check->fetchDuplicates($firstKey);
}
