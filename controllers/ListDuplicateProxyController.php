<?php

if (!function_exists('tmp')) {
  require_once __DIR__ . '/../func.php';
}

use PhpProxyHunter\BaseController;

class ListDuplicateProxyController extends BaseController
{
  private $outputFile;
  private $lock;

  public function __construct()
  {
    parent::__construct(); // Ensure BaseController's constructor runs
    $this->outputFile = unixPath(tmp() . '/proxies/' . get_class($this) . '.txt');
    $this->lock = new \PhpProxyHunter\FileLockHelper($this->getLockFilePath());
  }

  public function indexAction()
  {
    if ($this->lock->lock()) {
      $urlInfo = $this->getCurrentUrlInfo();
      $max = isset($urlInfo['query_params']['max']) ? intval($urlInfo['query_params']['max']) : 100;
      $page = isset($urlInfo['query_params']['page']) ? intval($urlInfo['query_params']['page']) : 1;

      $cmd = "php " . escapeshellarg(getProjectRoot() . '/controllers/ListDuplicateProxyController.php')
        . ' --max=' . escapeshellarg($max)
        . ' --page=' . escapeshellarg($page);
      $this->executeCommand($cmd);
    }

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
      . ' --lockFile=' . escapeshellarg($this->lockFilePath)
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

    if ($this->lock->isLockedByAnotherProcess()) {
      $this->log("[CHECK-DUPLICATE] Another instance is running, exiting.");
      return;
    }

    if ($this->lock->lock()) {
      $db = new \PhpProxyHunter\ProxyDB();
      if ($db->isDatabaseLocked()) {
        $this->log("[CHECK-DUPLICATE] Database is locked, exiting.");
        return;
      }
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

      // Sort by count descending
      uasort($duplicated_ip, function ($a, $b) {
        return count($b) <=> count($a);
      });

      // Write JSON data to file (not compressed here)
      write_file($this->outputFile, json_encode($duplicated_ip, JSON_PRETTY_PRINT));

      return $duplicated_ip;
    }

    return [];
  }
}

// Only run when executed directly from CLI, not when included or required
if (php_sapi_name() === 'cli' && realpath(__FILE__) === realpath($_SERVER['argv'][0] ?? '')) {
  // Parse CLI arguments for --page=[n] and --max=[n] using getopt
  $options = getopt('', ['page::', 'max::']);

  $page = isset($options['page']) ? intval($options['page']) : 1;
  $pageSize = isset($options['max']) ? intval($options['max']) : 1000;

  $controller = new ListDuplicateProxyController();
  $data = $controller->fetchDuplicates($page, $pageSize);

  $result = array_map(function ($key, $value) {
    return "$key: " . count($value) . " proxies";
  }, array_keys($data), $data);

  $controller->log($result);
}
