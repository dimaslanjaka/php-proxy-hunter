<?php

namespace PhpProxyHunter;

use PDO;
use PDOException;

/**
 * Class ProxyDB
 *
 * @package PhpProxyHunter
 */
class ProxyDB {
  /** @var SQLiteHelper|MySQLHelper $db */
  public $db;
  /**
   * @var string The root directory of the project.
   */
  public $projectRoot;

  /**
   * ProxyDB constructor.
   *
   * @param string|SQLiteHelper|MySQLHelper|CoreDB|null $dbLocation Path to DB file, or DB helper/CoreDB instance.
   * @param string $dbType Database type: 'sqlite' or 'mysql'.
   * @param string $host MySQL host (for MySQL only).
   * @param string $dbname MySQL database name (for MySQL only).
   * @param string $username MySQL username (for MySQL only).
   * @param string $password MySQL password (for MySQL only).
   * @param bool $unique Whether to enforce unique constraints (MySQL only).
   */
  public function __construct(
    $dbLocation = null,
    string $dbType = 'sqlite',
    string $host = 'localhost',
    string $dbname = 'php_proxy_hunter',
    string $username = 'root',
    string $password = '',
    bool $unique = false
  ) {
    // Accept helper instance directly
    if ($dbLocation instanceof SQLiteHelper || $dbLocation instanceof MySQLHelper) {
      $this->db = $dbLocation;
      return;
    } elseif ($dbLocation instanceof CoreDB) {
      $this->db = $dbLocation->db;
      return;
    }

    // Resolve project root via Composer autoloader
    $autoloadPath      = (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName();
    $this->projectRoot = dirname(dirname(dirname($autoloadPath)));

    if ($dbType === 'mysql') {
      $this->initMySQL($host, $dbname, $username, $password, $unique);
    } else {
      $this->initSQLite($dbLocation);
    }
  }

  /**
   * Initialize MySQL database connection and schema.
   */
  private function initMySQL(string $host, string $dbname, string $username, string $password, bool $unique = false): void {
    $this->db = new MySQLHelper($host, $dbname, $username, $password, $unique);
    $sqlFile  = __DIR__ . '/assets/mysql-schema.sql';
    if (!is_file($sqlFile)) {
      throw new \RuntimeException("Failed to read SQL file: $sqlFile");
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
      throw new \RuntimeException("Failed to read SQL file: $sqlFile");
    }
    $this->db->pdo->exec($sql);
  }

  /**
   * Initialize SQLite database connection and schema.
   */
  private function initSQLite($dbLocation = null): void {
    $isInMemory = $dbLocation === ':memory:';
    $dbLocation = $isInMemory
      ? ':memory:'
      : ($dbLocation ?: $this->projectRoot . '/src/database.sqlite');

    // Ensure directory exists if not using in-memory DB
    if (!$isInMemory && !file_exists($dbLocation)) {
      $directory = dirname($dbLocation);
      if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new \RuntimeException("Failed to create directory: $directory");
      }
    }

    // Initialize SQLite
    $this->db = new SQLiteHelper($dbLocation);

    // Load schema
    $sqlFile = $this->projectRoot . '/assets/database/create.sql';
    $sql     = @file_get_contents($sqlFile);
    if ($sql === false) {
      throw new \RuntimeException("Failed to read SQL file: $sqlFile");
    }
    $this->db->pdo->exec($sql);

    // Enable WAL mode if not already enabled
    if (!$this->getMetaValue('wal_enabled')) {
      $this->db->pdo->exec('PRAGMA journal_mode = WAL');
      $this->setMetaValue('wal_enabled', '1');
    }

    // Enable auto-vacuum if not already enabled
    if (!$this->getMetaValue('auto_vacuum_enabled')) {
      $this->db->pdo->exec('PRAGMA auto_vacuum = FULL');
      $this->setMetaValue('auto_vacuum_enabled', '1');
    }

    // Daily maintenance
    $this->runDailyVacuum();
  }

  /**
   * Get a meta value from the meta table.
   *
   * @param string $key
   * @return string|null
   */
  private function getMetaValue($key) {
    $stmt = $this->db->pdo->prepare('SELECT value FROM meta WHERE key = :key');
    $stmt->execute(['key' => $key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['value'] : null;
  }

  /**
   * Set a meta value in the meta table.
   *
   * @param string $key
   * @param string $value
   */
  private function setMetaValue($key, $value) {
    $stmt = $this->db->pdo->prepare('REPLACE INTO meta (key, value) VALUES (:key, :value)');
    $stmt->execute(['key' => $key, 'value' => $value]);
  }

  /**
   * Run VACUUM if it has not been run in the last 24 hours.
   */
  private function runDailyVacuum() {
    $lastVacuumTime  = $this->getMetaValue('last_vacuum_time');
    $currentTime     = time();
    $oneDayInSeconds = 86400;

    if (!$lastVacuumTime || ($currentTime - (int)$lastVacuumTime > $oneDayInSeconds)) {
      $this->db->pdo->exec('VACUUM');
      $this->db->pdo->exec('PRAGMA integrity_check');
      $this->setMetaValue('last_vacuum_time', (string)$currentTime);
    }
  }

  /**
   * Get the appropriate RANDOM function based on database type
   * @return string
   */
  private function getRandomFunction() {
    return $this->db instanceof MySQLHelper ? 'RAND()' : 'RANDOM()';
  }

  /**
   * @param callable $callback
   * @return void
   */
  public function iterateAllProxies($callback) {
    try {
      $stmt = $this->db->pdo->query('SELECT * FROM proxies');
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        call_user_func($callback, $row);
      }
    } catch (PDOException $e) {
      echo 'Error: ' . $e->getMessage();
    }
  }

  /**
   * Normalize a proxy string into a canonical proxy representation.
   *
   * This method will:
   * - Trim surrounding whitespace from the provided input.
   * - Use the helper function extractProxies() to parse proxy entries from the input.
   * - Return the single normalized proxy string if exactly one proxy is found.
   *
   * Notes:
   * - If the input contains no valid proxies, the method returns null.
   * - If the input contains more than one proxy, an InvalidArgumentException is thrown.
   *
   * @param string|null $proxy The proxy input to normalize (may be a raw string containing whitespace or other text).
   * @return string|null The normalized proxy string, or null if no valid proxy could be extracted.
   * @throws \InvalidArgumentException If the input contains more than one proxy.
   */
  public function normalizeProxy($proxy) {
    $data    = trim($proxy);
    $extract = extractProxies($data, null, false);
    if (empty($extract)) {
      throw new \InvalidArgumentException('Input does not contain any valid proxy.');
    }
    // throw when proxy more than one
    if (count($extract) > 1) {
      throw new \InvalidArgumentException('Input contains more than one proxy.');
    }
    $data = $extract[0]->proxy;
    return $data;
  }

  /**
   * @param string $proxy
   * @return array
   */
  public function select($proxy) {
    $proxy = $this->normalizeProxy($proxy);
    return $this->db->select('proxies', '*', 'proxy = ?', [$proxy]);
  }

  /**
   * Get all proxies with optional randomization and pagination.
   *
   * Backwards-compatible: when only $limit is provided it behaves like before
   * (providing a positive limit implies randomization unless $randomize is set).
   *
   * @param int|null $limit Legacy single-argument limit (kept for compatibility).
   * @param bool|null $randomize When true, return results in random order. When false, return in default order. If null (default), preserve previous behaviour where providing a positive $limit implied randomization.
   * @param int|null $page 1-based page number for pagination. If provided together with $perPage, it overrides legacy $limit.
   * @param int|null $perPage Number of items per page for pagination.
   * @return array
   */
  public function getAllProxies($limit = null, $randomize = null, $page = null, $perPage = null) {
    // Determine ordering (random or not)
    if ($randomize === null) {
      $orderBy = ($limit !== null && $limit > 0) ? $this->getRandomFunction() : null;
    } else {
      $orderBy = ($randomize === true) ? $this->getRandomFunction() : null;
    }

    // Pagination (page/perPage) takes precedence over legacy $limit
    $offset     = null;
    $finalLimit = $limit;
    if ($page !== null && $perPage !== null) {
      $page       = max(1, (int)$page);
      $perPage    = max(0, (int)$perPage);
      $offset     = ($page - 1) * $perPage;
      $finalLimit = $perPage;
    }

    return $this->db->select('proxies', '*', null, [], $orderBy, $finalLimit, $offset);
  }

  /**
   * @param string $proxy
   */
  public function remove($proxy) {
    $proxy = $this->normalizeProxy($proxy);
    $this->db->delete('proxies', 'proxy = ?', [$proxy]);
    // Also remove from added_proxies to keep state consistent
    if (isset($this->db->pdo)) {
      $stmt = $this->db->pdo->prepare('DELETE FROM added_proxies WHERE proxy = :proxy');
      $stmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
      $stmt->execute();
    }
  }

  /**
   * @param string|null $proxy
   */
  public function add($proxy) {
    $proxy    = $this->normalizeProxy($proxy);
    $inserted = $this->db->insert('proxies', ['proxy' => $proxy, 'status' => 'untested'], true);
    if ($inserted) {
      // Also record in added_proxies so the proxy is treated as already added
      // markAsAdded will avoid duplicate entries.
      $this->markAsAdded($proxy);
    }
  }

  /**
   * @param string|null $proxy
   * @return bool
   */
  public function isAlreadyAdded($proxy) {
    $proxy = $this->normalizeProxy($proxy);
    $stmt  = $this->db->pdo->prepare('SELECT COUNT(*) FROM added_proxies WHERE proxy = :proxy');
    $stmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn() > 0;
  }

  /**
   * @param string|null $proxy
   */
  public function markAsAdded($proxy) {
    if ($this->isAlreadyAdded($proxy)) {
      return;
    }
    $stmt = $this->db->pdo->prepare('INSERT INTO added_proxies (proxy) VALUES (:proxy)');
    $stmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
    $stmt->execute();
  }

  /**
   * @param string $proxy
   * @param string|null $type
   * @param string|null $region
   * @param string|null $city
   * @param string|null $country
   * @param string|null $status
   * @param string|null $latency
   * @param string|null $timezone
   */
  public function update($proxy, $type = null, $region = null, $city = null, $country = null, $status = null, $latency = null, $timezone = null) {
    if (empty($this->select($proxy))) {
      $this->add($proxy);
    }
    $data = [];
    if ($city) {
      $data['city'] = $city;
    }
    if ($country) {
      $data['country'] = $country;
    }
    if ($type) {
      $data['type'] = $type;
    }
    if ($region) {
      $data['region'] = $region;
    }
    if ($latency) {
      $data['latency'] = $latency;
    }
    if ($timezone) {
      $data['timezone'] = $timezone;
    }
    if ($status) {
      $data['status'] = $status;
    }
    if (!empty($data)) {
      $this->updateData($proxy, $data);
    }
  }

  /**
   * @param string $proxy
   * @param array $data
   * @param bool $update_time
   */
  public function updateData($proxy, $data = [], $update_time = true) {
    if (empty($this->select($proxy))) {
      $this->add($proxy);
    }
    $data = array_filter($data, function ($value) {
      return $value !== null && $value !== false;
    });
    if (!empty($data['status']) && $data['status'] != 'untested' && $update_time) {
      $data['last_check'] = date(DATE_RFC3339);
    }
    if (!empty($data)) {
      // Remove values that are null, false, or empty (including '', 0, '0', [], etc.)
      $data = array_filter($data, function ($value) {
        return !empty($value) || $value === 0 || $value === '0';
      });
      // Update the record
      $this->db->update('proxies', $data, 'proxy = ?', [trim($proxy)]);
    }
  }

  public function updateStatus($proxy, $status) {
    $this->update(trim($proxy), null, null, null, null, $status, null);
  }

  public function updateLatency($proxy, $latency) {
    $this->update(trim($proxy), null, null, null, null, null, $latency);
  }

  public function getWorkingProxies($limit = null) {
    $whereClause = 'status = ?';
    $params      = ['active'];
    $orderBy     = ($limit !== null && $limit > 0) ? $this->getRandomFunction() : null;
    $result      = $this->db->select('proxies', '*', $whereClause, $params, $orderBy, $limit);
    return $result ?: [];
  }

  public function getPrivateProxies($limit = null) {
    $whereClause = 'status = ? OR private = ?';
    $params      = ['private', 'true'];
    $orderBy     = ($limit !== null && $limit > 0) ? $this->getRandomFunction() : null;
    return $this->db->select('proxies', '*', $whereClause, $params, $orderBy, $limit);
  }

  public function getDeadProxies($limit = null) {
    $whereClause = 'status = ? OR status = ?';
    $params      = ['dead', 'port-closed'];
    $orderBy     = ($limit !== null && $limit > 0) ? $this->getRandomFunction() : null;
    return $this->db->select('proxies', '*', $whereClause, $params, $orderBy, $limit);
  }

  public function getUntestedProxies($limit = null) {
    $whereClause = 'status IS NULL OR status = "" OR status NOT IN (?, ?, ?)';
    $params      = ['active', 'port-closed', 'dead'];
    $orderBy     = ($limit !== null && $limit > 0) ? $this->getRandomFunction() : null;
    return $this->db->select('proxies', '*', $whereClause, $params, $orderBy, $limit);
  }

  /**
   * Get proxies that have been tested previously, ordered by oldest last_check first.
   *
   * @param int|null $limit
   * @return array
   */
  public function getOldestTestedProxies($limit = null) {
    // Select proxies that have a last_check value and order ascending (oldest first)
    $whereClause = 'last_check IS NOT NULL AND last_check != ""';
    $orderBy     = 'last_check ASC';
    return $this->db->select('proxies', '*', $whereClause, [], $orderBy, $limit) ?: [];
  }

  public function countDeadProxies() {
    $closed = $this->db->count('proxies', 'status = ?', ['port-closed']);
    $dead   = $this->db->count('proxies', 'status = ?', ['dead']);
    return $closed + $dead;
  }

  public function countUntestedProxies() {
    return $this->db->count('proxies', 'status = ? OR status IS NULL OR status = "" OR status = "untested"', ['']);
  }

  public function countWorkingProxies() {
    return $this->db->count('proxies', "(status = ?) AND (private = ? OR private IS NULL OR private = '')", [
      'active',
      'false',
    ]);
  }

  public function countPrivateProxies() {
    return $this->db->count('proxies', 'private = ?', ['true']);
  }

  public function countAllProxies() {
    return $this->db->count('proxies');
  }

  public function close() {
    if ($this->db) {
      $this->db->close();
    }
    $this->db = null;
  }

  public function isDatabaseLocked() {
    return $this->db->isDatabaseLocked();
  }
}
