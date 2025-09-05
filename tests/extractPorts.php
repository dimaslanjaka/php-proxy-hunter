<?php

require_once __DIR__ . '/../func-proxy.php';

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

$db = new ProxyDB(__DIR__ . '/../tmp/database.sqlite');

$dead     = extractIpPortFromFile(__DIR__ . '/../dead.txt', true);
$untested = extractIpPortFromFile(__DIR__ . '/../proxies.txt', true);
$proxies  = array_merge($dead, $untested);
$str      = 'username1:password1@1.1.1.1:83
1.1.1.1:8080@username2:password2
XXXXX192.198.1.100:80XXX
138.118.104.166:999
45.4.253.133:999
185.26.197.13:8080
8.213.129.15:2
hello 8.213.129.15:2 *x';

$extract = extractProxies($str, $db);
$format  = array_map(function (Proxy $item) {
  if (!is_null($item->username) && !is_null($item->password)) {
    // var_dump($item->proxy);
    return $item->proxy . '@' . $item->username . ':' . $item->password;
  }
  return $item->proxy;
}, $extract);
var_dump($format);


function get_ports()
{
  global $proxies;
  $ports = array_map(function ($proxy) {
    // Split the proxy string by ":" and get the port part
    $parts = explode(':', $proxy);
    return end($parts); // Get the last element of the array which is the port
  }, $proxies);

  $unique_ports = array_unique($ports);

  echo json_encode($unique_ports);
}
