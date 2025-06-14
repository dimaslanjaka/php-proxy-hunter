<?php

if (!function_exists('tmp')) {
  require_once __DIR__ . '/../func.php';
}

use PhpProxyHunter\BaseController;

class ListDuplicateProxyController extends BaseController
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

  public function checkAction()
  {
    $cmd = "php " . escapeshellarg($_SERVER['SCRIPT_FILENAME']);

    $uid = getUserId();
    $cmd .= " --userId=" . escapeshellarg($uid);
    $cmd .= " --max=" . escapeshellarg("30");
    $cmd .= " --admin=" . escapeshellarg($this->isAdmin ? 'true' : 'false');

    $urlInfo = $this->getCurrentUrlInfo();
    if ($urlInfo) {
      $cmd .= " -ip=" . escapeshellarg($urlInfo['query_params']['ip'] ?? '');
    }

    // Generate the command to run in the background
    $cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($this->logFilePath), escapeshellarg($this->lockFilePath));
    $ext = (strtoupper(PHP_OS_FAMILY) === 'WINDOWS') ? '.bat' : '.sh';
    $runner = tmp() . "/runners/" . basename($this->lockFilePath, '.lock') . $ext;

    // Write the command to the runner script
    write_file($runner, $cmd);

    // Execute the runner script in the background
    runBashOrBatch($runner);

    $result = [
      'status' => 'success',
      'message' => 'Duplicate proxy check started. Check the log file for progress.',
      'user_id' => $uid,
    ];
    if ($this->isAdmin) {
      $result['log_file'] = $this->logFilePath;
      $result['lock_file'] = $this->lockFilePath;
      $result['output_file'] = $this->outputFile;
    }

    return $result;
  }

  public function fetchDuplicates()
  {
    if (!$this->isCLI) {
      throw new Exception('Only CLI Allowed');
    }

    $db = new \PhpProxyHunter\ProxyDB();
    /**
     * @var PDO
     */
    $pdo = $db->db->pdo;

    $sql = "
            SELECT * FROM proxies
            WHERE
              substr(proxy, 1, instr(proxy, ':') - 1) IN (
                SELECT substr(proxy, 1, instr(proxy, ':') - 1) AS ip
                FROM proxies
                GROUP BY ip
                HAVING COUNT(*) > 1
              )
            ORDER BY proxy;
        ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $duplicated_ip = [];

    foreach ($rows as $row) {
      // Extract IP from proxy (before ':')
      $ip = strstr($row['proxy'], ':', true);

      if ($ip === false) {
        // Skip rows without a valid proxy format (no colon found)
        continue;
      }

      // Group rows by IP
      $duplicated_ip[$ip][] = $row;
    }

    write_file($this->outputFile, json_encode($duplicated_ip));

    return $duplicated_ip;
  }
}

// Only run when executed directly from CLI, not when included or required
if (php_sapi_name() === 'cli' && realpath(__FILE__) === realpath($_SERVER['argv'][0] ?? '')) {
  $controller = new ListDuplicateProxyController();
  $data = $controller->fetchDuplicates();

  $result = array_map(function ($key, $value) {
    return "$key: " . count($value) . " proxies";
  }, array_keys($data), $data);

  print_r($result);
}
