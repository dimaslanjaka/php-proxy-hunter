<?php

namespace PhpProxyHunter;

if (!defined('PHP_PROXY_HUNTER')) exit('access denied');

class ProxyDB
{
  private $db;
  public function __construct($dbLocation = __DIR__ . '/database.sqlite')
  {
    $this->db = new SQLiteHelper($dbLocation);
  }

  public function select(string $proxy)
  {
    return $this->db->select('proxies', '*', 'proxy = ?', [trim($proxy)]);
  }

  public function getAllProxies()
  {
    return $this->db->select('proxies', '*');
  }

  public function remove(string $proxy)
  {
    $this->db->delete('proxies', 'proxy = ?', [trim($proxy)]);
  }

  public function add(string $proxy)
  {
    $this->db->insert('proxies', ['proxy' => trim($proxy)]);
  }

  /**
   * insert proxy or update when not exist
   */
  public function update(string $proxy, string $type = null, string $region = null, string $city = null, string $country = null, string $status = null, string $latency = null, string $timezone = null)
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

  public function updateData(string $proxy, array $data = [])
  {
    if (empty($this->select($proxy))) {
      $this->add($proxy);
    }
    // Remove null and false values
    $data = array_filter($data, function ($value) {
      return $value !== null && $value !== false;
    });
    if (!empty($data)) $this->db->update('proxies', $data, 'proxy = ?', [trim($proxy)]);
  }

  public function updateStatus(string $proxy, string $status)
  {
    $this->update(trim($proxy), null, null, null, null, $status, null);
  }

  public function updateLatency(string $proxy, string $latency)
  {
    $this->update(trim($proxy), null, null, null, null, null, $latency);
  }

  public function getWorkingProxies()
  {
    $result = $this->db->select('proxies', '*', 'status = ?', ['active']);
    if (!$result) return [];
    return $result;
  }

  public function getPrivateProxies()
  {
    $result = $this->db->select('proxies', '*', 'status = ?', ['private']);
    if (!$result) return [];
    return $result;
  }

  /**
   * get dead proxies including closed port
   */
  public function getDeadProxies()
  {
    $result = $this->db->select('proxies', '*', 'status = ?', ['dead']);
    $result2 = $this->db->select('proxies', '*', 'status = ?', ['port-closed']);
    if (!is_array($result)) $result = [];
    if (!is_array($result2)) $result2 = [];
    return array_merge($result, $result2);
  }
}
