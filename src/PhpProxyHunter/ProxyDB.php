<?php

namespace PhpProxyHunter;

use PDO;
use PDOException;

if (!defined('PHP_PROXY_HUNTER')) {
  exit('access denied');
}

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
   * ProxyDB constructor.
   *
   * @param string|null $dbLocation
   */
  public function __construct(?string $dbLocation = null)
  {
    if (!$dbLocation) {
      $dbLocation = __DIR__ . '/../database.sqlite';
    }
    $this->db = new SQLiteHelper($dbLocation);

    // Initialize the database schema
    $sqlFileContents = file_get_contents(__DIR__ . '/../../assets/database/create.sql');
    if ($sqlFileContents === false) {
      die('Error reading SQL file.');
    }
    // Split the file contents by semicolon to get individual commands
    $sqlCommands = explode(';', $sqlFileContents);

    // Trim whitespace from each command and filter out empty commands
    $sqlCommands = array_filter(array_map('trim', $sqlCommands));

    foreach ($sqlCommands as $command) {
      if (!empty($command)) {
        $this->db->pdo->exec($command);
      }
    }

    // Check if WAL mode has been enabled
    $walEnabled = $this->getMetaValue('wal_enabled');
    if (!$walEnabled) {
      // Enable Write-Ahead Logging mode
      $this->db->pdo->exec('PRAGMA journal_mode = WAL');
      $this->setMetaValue('wal_enabled', '1');
    }

    // Check if auto-vacuum mode has been enabled
    $autoVacuumEnabled = $this->getMetaValue('auto_vacuum_enabled');
    if (!$autoVacuumEnabled) {
      // Enable auto-vacuum mode
      $this->db->pdo->exec('PRAGMA auto_vacuum = FULL');
      $this->setMetaValue('auto_vacuum_enabled', '1');
    }

    // Check if VACUUM needs to be run
    $this->runDailyVacuum();
  }

  /**
   * Get a meta value from the meta table.
   *
   * @param string $key
   * @return string|null
   */
  private function getMetaValue(string $key): ?string
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
  private function setMetaValue(string $key, string $value): void
  {
    $stmt = $this->db->pdo->prepare('REPLACE INTO meta (key, value) VALUES (:key, :value)');
    $stmt->execute(['key' => $key, 'value' => $value]);
  }

  /**
   * Run VACUUM if it has not been run in the last 24 hours.
   */
  private function runDailyVacuum(): void
  {
    $lastVacuumTime = $this->getMetaValue('last_vacuum_time');
    $currentTime = time();
    $oneDayInSeconds = 86400;

    if (!$lastVacuumTime || ($currentTime - (int)$lastVacuumTime > $oneDayInSeconds)) {
      // Execute the VACUUM command to reclaim unused space
      $this->db->pdo->exec('VACUUM');
      // check pragma
      $this->db->pdo->exec('PRAGMA integrity_check');
      $this->setMetaValue('last_vacuum_time', (string)$currentTime);
    }
  }

  /**
   * Iterate over all proxies in the database and apply a callback to each.
   *
   * @param callable $callback The callback function to apply to each proxy row.
   *                           The callback should accept a single parameter, which is an associative array representing a row from the proxies table:
   *                           function(array $row): void
   * @return void
   */
  public function iterateAllProxies(callable $callback): void
  {
    try {
      // Execute a query to fetch large rows
      $stmt = $this->db->pdo->query('SELECT * FROM proxies');

      // Iterate over the result set using a while loop
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Process each row using the callback function
        call_user_func($callback, $row);
      }
    } catch (PDOException $e) {
      // Handle database connection errors
      echo 'Error: ' . $e->getMessage();
    }
  }

  /**
   * Selects a proxy from the database.
   *
   * @param string $proxy
   * @return array
   */
  public function select(string $proxy): array
  {
    return $this->db->select('proxies', '*', 'proxy = ?', [trim($proxy)]);
  }

  /**
   * Get all proxies from the database.
   *
   * @param int|null $limit The maximum number of proxies to retrieve. Default is PHP_INT_MAX (no limit).
   * @return array An array containing the proxies.
   */
  public function getAllProxies(?int $limit = null): array
  {
    // Construct the SQL query string
    $sql = 'SELECT * FROM proxies';

    // Append the limit clause if the $limit parameter is provided
    if ($limit !== null) {
      $sql .= ' ORDER BY RANDOM() LIMIT ?';
    }

    // Prepare the statement
    $stmt = $this->db->pdo->prepare($sql);

    // Bind the limit parameter if provided
    if ($limit !== null) {
      $stmt->bindParam(1, $limit, \PDO::PARAM_INT);
    }

    // Execute the query
    $stmt->execute();

    // Fetch the result
    $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Check for result and return
    return $result ?: [];
  }

  /**
   * Removes a proxy from the database.
   *
   * @param string $proxy
   */
  public function remove(string $proxy): void
  {
    $this->db->delete('proxies', 'proxy = ?', [trim($proxy)]);
  }

  /**
   * Adds a proxy to the database.
   *
   * @param string|null $proxy The proxy string to add to the database.
   * @param bool $force Whether to force add the proxy even if it's already added.
   */
  public function add(?string $proxy, bool $force = false): void
  {
    $this->db->insert('proxies', ['proxy' => trim($proxy), 'status' => 'untested']);
    // if (!$this->isAlreadyAdded($proxy) || $force) {
    //   $this->db->insert('proxies', ['proxy' => trim($proxy), 'status' => 'untested']);
    //   if (!$force) {
    //     $this->markAsAdded($proxy);
    //   }
    // }
  }

  /**
   * Checks if a proxy is already added to the database.
   *
   * @param string|null $proxy The proxy string to check.
   * @return bool Returns true if the proxy is already added, false otherwise.
   */
  public function isAlreadyAdded(?string $proxy): bool
  {
    if (empty($proxy)) {
      return false;
    }
    $stmt = $this->db->pdo->prepare("SELECT COUNT(*) FROM added_proxies WHERE proxy = :proxy");
    $stmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn() > 0;
  }

  /**
   * Marks a proxy as added in the database.
   *
   * @param string|null $proxy The proxy string to mark as added.
   */
  public function markAsAdded(?string $proxy): void
  {
    if ($this->isAlreadyAdded($proxy)) {
      return;
    }
    $stmt = $this->db->pdo->prepare('INSERT INTO added_proxies (proxy) VALUES (:proxy)');
    $stmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
    $stmt->execute();
  }

  /**
   * Inserts or updates a proxy in the database.
   *
   * @param string $proxy
   * @param string|null $type
   * @param string|null $region
   * @param string|null $city
   * @param string|null $country
   * @param string|null $status
   * @param string|null $latency
   * @param string|null $timezone
   */
  public function update(string $proxy, ?string $type = null, ?string $region = null, ?string $city = null, ?string $country = null, ?string $status = null, ?string $latency = null, ?string $timezone = null): void
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
   * Updates data for a specific proxy.
   *
   * @param string $proxy
   * @param array $data
   * @param bool $update_time
   */
  public function updateData(string $proxy, array $data = [], bool $update_time = true): void
  {
    if (empty($this->select($proxy))) {
      $this->add($proxy);
    }
    // Remove null and false values
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

  /**
   * Updates the status of a proxy.
   *
   * @param string $proxy
   * @param string $status
   */
  public function updateStatus(string $proxy, string $status): void
  {
    $this->update(trim($proxy), null, null, null, null, $status, null);
  }

  /**
   * Updates the latency of a proxy.
   *
   * @param string $proxy
   * @param string $latency
   */
  public function updateLatency(string $proxy, string $latency): void
  {
    $this->update(trim($proxy), null, null, null, null, null, $latency);
  }

  /**
   * Gets working proxies from the database.
   *
   * @param int|null $limit The maximum number of working proxies to retrieve. Default is null (no limit).
   * @return array An array containing the working proxies.
   */
  public function getWorkingProxies(?int $limit = null): array
  {
    $whereClause = 'status = ?';
    $params = ['active'];

    $orderByRandom = ($limit !== null && $limit > 0) ? 'ORDER BY RANDOM()' : '';
    $limitClause = ($limit !== null) ? "LIMIT $limit" : '';

    $result = $this->db->select('proxies', '*', $whereClause . ' ' . $orderByRandom . ' ' . $limitClause, $params);
    if (!$result) {
      return [];
    }
    return $result;
  }

  /**
   * Gets private proxies from the database.
   *
   * @param int|null $limit The maximum number of private proxies to retrieve. Default is null (no limit).
   * @return array An array containing the private proxies.
   */
  public function getPrivateProxies(?int $limit = null): array
  {
    $whereClause = 'status = ? OR private = ?';
    $params = ['private', 'true'];

    $orderByRandom = ($limit !== null && $limit > 0) ? 'ORDER BY RANDOM()' : '';
    $limitClause = ($limit !== null) ? "LIMIT $limit" : '';

    return $this->db->select('proxies', '*', $whereClause . ' ' . $orderByRandom . ' ' . $limitClause, $params);
  }

  /**
   * Gets dead proxies from the database, including those with closed ports.
   *
   * @param int|null $limit The maximum number of dead proxies to retrieve. Default is null (no limit).
   * @return array An array containing the dead proxies.
   */
  public function getDeadProxies(?int $limit = null): array
  {
    $whereClause = 'status = ? OR status = ?';
    $params = ['dead', 'port-closed'];

    $orderByRandom = ($limit !== null && $limit > 0) ? 'ORDER BY RANDOM()' : '';
    $limitClause = ($limit !== null) ? "LIMIT $limit" : '';

    return $this->db->select('proxies', '*', $whereClause . ' ' . $orderByRandom . ' ' . $limitClause, $params);
  }

  /**
   * Retrieves untested proxies from the database.
   *
   * @param int|null $limit The maximum number of untested proxies to retrieve. Default is null (no limit).
   * @return array An array containing the untested proxies.
   */
  public function getUntestedProxies(?int $limit = null): array
  {
    $whereClause = 'status IS NULL OR status = "" OR status NOT IN (?, ?, ?)';
    $params = ['active', 'port-closed', 'dead'];

    $orderByRandom = ($limit !== null && $limit > 0) ? 'ORDER BY RANDOM()' : '';
    $limitClause = ($limit !== null) ? "LIMIT $limit" : '';

    return $this->db->select('proxies', '*', $whereClause . ' ' . $orderByRandom . ' ' . $limitClause, $params);
  }

  public function countDeadProxies(): int
  {
    $closed = $this->db->count('proxies', 'status = ?', ['port-closed']);
    $dead = $this->db->count('proxies', 'status = ?', ['dead']);
    return $closed + $dead;
  }

  public function countUntestedProxies(): int
  {
    return $this->db->count('proxies', 'status = ? OR status IS NULL OR status = "" OR status = "untested"', ['']);
  }

  public function countWorkingProxies(): int
  {
    return $this->db->count('proxies', "(status = ?) AND (private = ? OR private IS NULL OR private = '')", [
      'active',
      'false'
    ]);
  }

  public function countPrivateProxies(): int
  {
    return $this->db->count('proxies', "private = ?", ['true']);
  }

  public function countAllProxies(): int
  {
    return $this->db->count('proxies');
  }

  public function close()
  {
    $this->db->close();
    $this->db = null;
  }
}

/**
 * proxy table data class
 */
class Proxy
{
  /** @var int|null */
  public $id = null;

  /** @var string */
  public $proxy;

  /** @var string|null */
  public $latency = null;

  /** @var string|null */
  public $type = null;

  /** @var string|null */
  public $region = null;

  /** @var string|null */
  public $city = null;

  /** @var string|null */
  public $country = null;

  /** @var string|null */
  public $last_check = null;

  /** @var string|null */
  public $anonymity = null;

  /** @var string|null */
  public $status = null;

  /** @var string|null */
  public $timezone = null;

  /** @var string|null */
  public $longitude = null;

  /** @var string|null */
  public $private = null;

  /** @var string|null */
  public $latitude = null;

  /** @var string|null */
  public $lang = null;

  /** @var string|null */
  public $useragent = null;

  /** @var string|null */
  public $webgl_vendor = null;

  /** @var string|null */
  public $webgl_renderer = null;

  /** @var string|null */
  public $browser_vendor = null;

  /** @var string|null */
  public $username = null;

  /** @var string|null */
  public $password = null;

  /**
   * Proxy constructor.
   * @param string $proxy
   * @param string|null $latency
   * @param string|null $type
   * @param string|null $region
   * @param string|null $city
   * @param string|null $country
   * @param string|null $last_check
   * @param string|null $anonymity
   * @param string|null $status
   * @param string|null $timezone
   * @param string|null $longitude
   * @param string|null $private
   * @param string|null $latitude
   * @param string|null $lang
   * @param string|null $useragent
   * @param string|null $webgl_vendor
   * @param string|null $webgl_renderer
   * @param string|null $browser_vendor
   * @param string|null $username
   * @param string|null $password
   * @param int|null $id
   */
  public function __construct(
    string  $proxy,
    ?string $latency = null,
    ?string $type = null,
    ?string $region = null,
    ?string $city = null,
    ?string $country = null,
    ?string $last_check = null,
    ?string $anonymity = null,
    ?string $status = null,
    ?string $timezone = null,
    ?string $longitude = null,
    ?string $private = null,
    ?string $latitude = null,
    ?string $lang = null,
    ?string $useragent = null,
    ?string $webgl_vendor = null,
    ?string $webgl_renderer = null,
    ?string $browser_vendor = null,
    ?string $username = null,
    ?string $password = null,
    ?int    $id = null
  ) {
    $this->id = $id;
    $this->proxy = $proxy;
    $this->latency = $latency;
    $this->type = $type;
    $this->region = $region;
    $this->city = $city;
    $this->country = $country;
    $this->last_check = $last_check;
    $this->anonymity = $anonymity;
    $this->status = $status;
    $this->timezone = $timezone;
    $this->longitude = $longitude;
    $this->private = $private;
    $this->latitude = $latitude;
    $this->lang = $lang;
    $this->useragent = $useragent;
    $this->webgl_vendor = $webgl_vendor;
    $this->webgl_renderer = $webgl_renderer;
    $this->browser_vendor = $browser_vendor;
    $this->username = $username;
    $this->password = $password;
  }
}
