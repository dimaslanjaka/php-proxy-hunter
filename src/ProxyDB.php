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
   * @param string $dbLocation
   */
  public function __construct(string $dbLocation = __DIR__ . '/database.sqlite')
  {
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
   * Gets all working proxies from the database.
   *
   * @return array
   */
  public function getWorkingProxies(): array
  {
    $result = $this->db->select('proxies', '*', 'status = ?', ['active']);
    if (!$result) return [];
    return $result;
  }

  /**
   * Gets all private proxies from the database.
   *
   * @return array
   */
  public function getPrivateProxies(): array
  {
    $result = $this->db->select('proxies', '*', 'status = ?', ['private']);
    if (!$result) return [];
    return $result;
  }

  /**
   * Gets all dead proxies from the database, including those with closed ports.
   *
   * @return array
   */
  public function getDeadProxies(): array
  {
    $result = $this->db->select('proxies', '*', 'status = ?', ['dead']);
    $result2 = $this->db->select('proxies', '*', 'status = ?', ['port-closed']);
    if (!is_array($result)) $result = [];
    if (!is_array($result2)) $result2 = [];
    return array_merge($result, $result2);
  }
}
