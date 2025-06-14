<?php

if (!function_exists('tmp')) {
  require_once __DIR__ . '/../func.php';
  require_once __DIR__ . '/autoload.php';
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

    $duplicated_ip = [];

    foreach ($rows as $row) {
      $proxy_ip = strstr($row['proxy'], ':', true);
      if ($proxy_ip === false) {
        continue; // Skip invalid proxy
      }

      if (!isset($duplicated_ip[$proxy_ip])) {
        $duplicated_ip[$proxy_ip] = [];
      }
      $duplicated_ip[$proxy_ip][] = $row['proxy'];
    }

    write_file($this->outputFile, json_encode($duplicated_ip, JSON_PRETTY_PRINT));
  }
}

// Only run when executed directly from CLI, not when included or required
if (php_sapi_name() === 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
  $controller = new ListDuplicateProxyController();
  $data = $controller->fetchDuplicates();
  $firstKey = array_key_first($data);
  $firstValue = $data[$firstKey];
  var_dump($firstKey);
  var_dump($firstValue);
}
