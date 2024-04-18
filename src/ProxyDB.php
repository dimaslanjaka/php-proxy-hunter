<?php

namespace PhpProxyHunter;

if (!defined('PHP_PROXY_HUNTER')) exit('access denied');

class ProxyDB
{
  private $db;
  public function __construct()
  {
    $this->db = new SQLiteHelper(__DIR__ . '/database.sqlite');
  }

  public function select(string $proxy)
  {
    return $this->db->select('proxies', '*', 'proxy = ?', [trim($proxy)]);;
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
    $this->db->update('proxies', $data, 'proxy = ?', [trim($proxy)]);
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
}
