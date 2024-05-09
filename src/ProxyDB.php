<?php

namespace PhpProxyHunter;

if (!defined('PHP_PROXY_HUNTER')) exit('access denied');

/**
 * Class ProxyDB
 *
 * @package PhpProxyHunter
 */
class ProxyDB
{
  /** @var SQLiteHelper $db */
  private $db;

  /**
   * ProxyDB constructor.
   *
   * @param string|null $dbLocation
   */
  public function __construct(?string $dbLocation = null)
  {
    if (!$dbLocation) $dbLocation = realpath(__DIR__ . '/database.sqlite');
    $this->db = new SQLiteHelper($dbLocation);
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
   * @return array
   */
  public function getAllProxies(): array
  {
    return $this->db->select('proxies', '*');
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
   * @param string $proxy
   */
  public function add(string $proxy): void
  {
    $this->db->insert('proxies', ['proxy' => trim($proxy), 'status' => 'untested']);
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
    if ($city) $data['city'] = $city;
    if ($country) $data['country'] = $country;
    if ($type) $data['type'] = $type;
    if ($region) $data['region'] = $region;
    if ($latency) $data['latency'] = $latency;
    if ($timezone) $data['timezone'] = $timezone;
    if ($status) {
      $data['status'] = $status;
      $data['last_check'] = date(DATE_RFC3339);
    }
    if (!empty($data)) $this->updateData($proxy, $data);
  }

  /**
   * Updates data for a specific proxy.
   *
   * @param string $proxy
   * @param array $data
   */
  public function updateData(string $proxy, array $data = []): void
  {
    if (empty($this->select($proxy))) {
      $this->add($proxy);
    }
    // Remove null and false values
    $data = array_filter($data, function ($value) {
      return $value !== null && $value !== false;
    });
    if (isset($data['status'])) $data['last_check'] = date(DATE_RFC3339);
    if (!empty($data)) $this->db->update('proxies', $data, 'proxy = ?', [trim($proxy)]);
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

    // Append the limit clause if the $limit parameter is provided
    $limitClause = ($limit !== null) ? "LIMIT $limit" : '';

    $result = $this->db->select('proxies', '*', $whereClause . ' ' . $limitClause, $params);
    if (!$result) return [];
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
    $whereClause = 'status = ?';
    $params = ['private'];

    // Append the limit clause if the $limit parameter is provided
    $limitClause = ($limit !== null) ? "LIMIT $limit" : '';

    $result = $this->db->select('proxies', '*', $whereClause . ' ' . $limitClause, $params);
    if (!$result) return [];
    return $result;
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

    // Append the limit clause if the $limit parameter is provided
    $limitClause = ($limit !== null) ? "LIMIT $limit" : '';

    return $this->db->select('proxies', '*', $whereClause . ' ' . $limitClause, $params);
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

    // Append the limit clause if the $limit parameter is provided
    $limitClause = ($limit !== null) ? "LIMIT $limit" : '';

    return $this->db->select('proxies', '*', $whereClause . ' ' . $limitClause, $params);
  }

  public function countDeadProxies(): int
  {
    $closed = $this->db->count('proxies', 'status = ?', ['port-closed']);
    $dead = $this->db->count('proxies', 'status = ?', ['dead']);
    return $closed + $dead;
  }

  public function countUntestedProxies(): int
  {
    return $this->db->count('proxies', 'status = ? OR status IS NULL OR status = ""', ['']);
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
  )
  {
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
