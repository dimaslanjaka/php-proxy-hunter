<?php

namespace PhpProxyHunter;

use PDO;
use PDOException;

/**
 * Class ProxyDB
 *
 * @package PhpProxyHunter
 */
class ProxyDB
{
  /** @var SQLiteHelper $db */
  public $db;
  /**
   * @var string The root directory of the project.
   */
  public $projectRoot;

  /**
   * ProxyDB constructor.
   *
   * @param string|SQLiteHelper|MySQLHelper|null $dbLocation Path to DB file, or DB helper instance.
   */
  public function __construct($dbLocation = null)
  {
    // Accept helper instance directly
    if ($dbLocation instanceof SQLiteHelper || $dbLocation instanceof MySQLHelper) {
      $this->db = $dbLocation;
      return;
    }

    // Resolve project root via Composer autoloader
    $autoloadPath      = (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName();
    $this->projectRoot = dirname(dirname(dirname($autoloadPath)));

    // Normalize database location
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
  private function getMetaValue($key)
  {
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
  private function setMetaValue($key, $value)
  {
    $stmt = $this->db->pdo->prepare('REPLACE INTO meta (key, value) VALUES (:key, :value)');
    $stmt->execute(['key' => $key, 'value' => $value]);
  }

  /**
   * Run VACUUM if it has not been run in the last 24 hours.
   */
  private function runDailyVacuum()
  {
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
   * @param callable $callback
   * @return void
   */
  public function iterateAllProxies($callback)
  {
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
   * @param string $proxy
   * @return array
   */
  public function select($proxy)
  {
    return $this->db->select('proxies', '*', 'proxy = ?', [trim($proxy)]);
  }

  /**
   * @param int|null $limit
   * @return array
   */
  public function getAllProxies($limit = null)
  {
    $sql = 'SELECT * FROM proxies';
    if ($limit !== null) {
      $sql .= ' ORDER BY RANDOM() LIMIT ?';
    }
    $stmt = $this->db->pdo->prepare($sql);
    if ($limit !== null) {
      $stmt->bindParam(1, $limit, \PDO::PARAM_INT);
    }
    $stmt->execute();
    $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    return $result ?: [];
  }

  /**
   * @param string $proxy
   */
  public function remove($proxy)
  {
    $this->db->delete('proxies', 'proxy = ?', [trim($proxy)]);
  }

  /**
   * @param string|null $proxy
   * @param bool $force
   */
  public function add($proxy, $force = false)
  {
    $this->db->insert('proxies', ['proxy' => trim($proxy), 'status' => 'untested']);
  }

  /**
   * @param string|null $proxy
   * @return bool
   */
  public function isAlreadyAdded($proxy)
  {
    if (empty($proxy)) {
      return false;
    }
    $stmt = $this->db->pdo->prepare('SELECT COUNT(*) FROM added_proxies WHERE proxy = :proxy');
    $stmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn() > 0;
  }

  /**
   * @param string|null $proxy
   */
  public function markAsAdded($proxy)
  {
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
  public function update($proxy, $type = null, $region = null, $city = null, $country = null, $status = null, $latency = null, $timezone = null)
  {
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
  public function updateData($proxy, $data = [], $update_time = true)
  {
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
      $this->db->update('proxies', $data, 'proxy = ?', [trim($proxy)]);
    }
  }

  public function updateStatus($proxy, $status)
  {
    $this->update(trim($proxy), null, null, null, null, $status, null);
  }

  public function updateLatency($proxy, $latency)
  {
    $this->update(trim($proxy), null, null, null, null, null, $latency);
  }

  public function getWorkingProxies($limit = null)
  {
    $whereClause   = 'status = ?';
    $params        = ['active'];
    $orderByRandom = ($limit !== null && $limit > 0) ? 'ORDER BY RANDOM()' : '';
    $limitClause   = ($limit !== null) ? "LIMIT $limit" : '';
    $result        = $this->db->select('proxies', '*', $whereClause . ' ' . $orderByRandom . ' ' . $limitClause, $params);
    return $result ?: [];
  }

  public function getPrivateProxies($limit = null)
  {
    $whereClause   = 'status = ? OR private = ?';
    $params        = ['private', 'true'];
    $orderByRandom = ($limit !== null && $limit > 0) ? 'ORDER BY RANDOM()' : '';
    $limitClause   = ($limit !== null) ? "LIMIT $limit" : '';
    return $this->db->select('proxies', '*', $whereClause . ' ' . $orderByRandom . ' ' . $limitClause, $params);
  }

  public function getDeadProxies($limit = null)
  {
    $whereClause   = 'status = ? OR status = ?';
    $params        = ['dead', 'port-closed'];
    $orderByRandom = ($limit !== null && $limit > 0) ? 'ORDER BY RANDOM()' : '';
    $limitClause   = ($limit !== null) ? "LIMIT $limit" : '';
    return $this->db->select('proxies', '*', $whereClause . ' ' . $orderByRandom . ' ' . $limitClause, $params);
  }

  public function getUntestedProxies($limit = null)
  {
    $whereClause   = 'status IS NULL OR status = "" OR status NOT IN (?, ?, ?)';
    $params        = ['active', 'port-closed', 'dead'];
    $orderByRandom = ($limit !== null && $limit > 0) ? 'ORDER BY RANDOM()' : '';
    $limitClause   = ($limit !== null) ? "LIMIT $limit" : '';
    return $this->db->select('proxies', '*', $whereClause . ' ' . $orderByRandom . ' ' . $limitClause, $params);
  }

  public function countDeadProxies()
  {
    $closed = $this->db->count('proxies', 'status = ?', ['port-closed']);
    $dead   = $this->db->count('proxies', 'status = ?', ['dead']);
    return $closed + $dead;
  }

  public function countUntestedProxies()
  {
    return $this->db->count('proxies', 'status = ? OR status IS NULL OR status = "" OR status = "untested"', ['']);
  }

  public function countWorkingProxies()
  {
    return $this->db->count('proxies', "(status = ?) AND (private = ? OR private IS NULL OR private = '')", [
      'active',
      'false',
    ]);
  }

  public function countPrivateProxies()
  {
    return $this->db->count('proxies', 'private = ?', ['true']);
  }

  public function countAllProxies()
  {
    return $this->db->count('proxies');
  }

  public function close()
  {
    $this->db->close();
    $this->db = null;
  }

  public function isDatabaseLocked()
  {
    return $this->db->isDatabaseLocked();
  }
}
