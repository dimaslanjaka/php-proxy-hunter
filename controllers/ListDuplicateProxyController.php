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
    $this->outputFile = unixPath(tmp() . '/proxies/' . get_class($this) . '.txt');
  }

  private function executeCommand($cmd)
  {
    // Generate the command to run in the background
    $cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($this->logFilePath), escapeshellarg($this->lockFilePath));
    $ext = (strtoupper(PHP_OS_FAMILY) === 'WINDOWS') ? '.bat' : '.sh';
    $runner = tmp() . "/runners/" . basename($this->lockFilePath, '.lock') . $ext;

    // Write the command to the runner script
    write_file($runner, $cmd);

    // Execute the runner script in the background
    runBashOrBatch($runner, [], getCallerInfo() . '-' . $this->session_id);

    return ['runner' => $runner, 'command' => $cmd];
  }

  public function indexAction()
  {
    $cmd = "php " . escapeshellarg(getProjectRoot() . '/controllers/ListDuplicateProxyController.php');
    $this->executeCommand($cmd);

    if (!file_exists($this->outputFile)) {
      return [];
    }
    return json_decode(read_file($this->outputFile) ?? '[]', true);
  }

  public function checkAction()
  {
    $urlInfo = $this->getCurrentUrlInfo();
    $ip = $urlInfo['query_params']['ip'] ?? null;
    if (!isValidIp($ip)) {
      return [
        'status' => 'error',
        'message' => 'Invalid IP address provided.',
        'ip' => $ip,
        'valid' => false,
      ];
    }

    $cmd = 'php ' . escapeshellarg(getProjectRoot() . '/controllers/CheckDuplicateProxyController.php')
      . ' --userId=' . escapeshellarg(getUserId())
      . ' --max=30'
      . ' --admin=' . escapeshellarg($this->isAdmin ? 'true' : 'false')
      . ' -ip=' . escapeshellarg($ip);

    $exec = $this->executeCommand($cmd);

    $result = [
      'status' => 'success',
      'message' => 'Duplicate proxy check started. Check the log file for progress.',
      'user_id' => getUserId(),
    ];

    if ($this->isAdmin) {
      $result += [
        'log_file' => $this->logFilePath,
        'lock_file' => $this->lockFilePath,
        'output_file' => $this->outputFile,
        'runner' => $exec['runner'],
        'command' => [
          'original' => $cmd,
          'modified' => $exec['command'],
        ],
      ];
    }

    return $result;
  }

  /**
   * Fetch duplicate proxies, paginated.
   *
   * @param int $page The page number (1-based index)
   * @param int $pageSize Number of records per page
   * @return array Grouped duplicate proxies by IP
   * @throws Exception
   */
  public function fetchDuplicates(int $page = 1, int $pageSize = 1000)
  {
    if (!$this->isCLI) {
      throw new Exception('Only CLI Allowed');
    }

    $db = new \PhpProxyHunter\ProxyDB();
    /**
     * @var PDO
     */
    $pdo = $db->db->pdo;

    // Calculate SQL offset
    $offset = ($page - 1) * $pageSize;

    $sql = "SELECT * FROM proxies
    WHERE
      substr(proxy, 1, instr(proxy, ':') - 1) IN (
        SELECT substr(proxy, 1, instr(proxy, ':') - 1) AS ip
        FROM proxies
        GROUP BY ip
        HAVING COUNT(*) > 1
      )
    ORDER BY proxy
    LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $duplicated_ip = [];

    // Fetch rows one by one to reduce memory usage
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      // Extract IP from proxy (before ':')
      $ip = strstr($row['proxy'], ':', true);

      if ($ip === false) {
        // Skip rows without a valid proxy format (no colon found)
        continue;
      }

      // Group rows by IP
      $duplicated_ip[$ip][] = $row;
    }

    // Write JSON data to file (not compressed here)
    write_file($this->outputFile, json_encode($duplicated_ip, JSON_PRETTY_PRINT));

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
