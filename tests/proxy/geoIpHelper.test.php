<?php

require_once __DIR__ . '/../../php_backend/shared.php';

use PhpProxyHunter\GeoIpHelper;

$connections = refreshDbConnections();
$proxy_db    = $connections['proxy_db'];
$proxies     = $proxy_db->db->select('proxies', ['proxy'], "country IS NULL OR country = '' OR timezone IS NULL OR timezone = ''", [], null, 10);
$randomProxy = !empty($proxies) ? $proxies[array_rand($proxies)] : null;
if (empty($randomProxy)) {
  echo "No proxies found with missing geo fields\n";
  exit(0);
}
$ipPort        = $randomProxy['proxy'];
$proxyUsername = isset($randomProxy['username']) ? $randomProxy['username'] : null;
$proxyPassword = isset($randomProxy['password']) ? $randomProxy['password'] : null;

$protocols = ['http', 'socks4', 'socks5', 'socks4a', 'socks5h'];
$hasWorked = false;
foreach ($protocols as $protocol) {
  $result = GeoIpHelper::resolveGeoProxy($ipPort, $protocol, $proxy_db, $proxyUsername, $proxyPassword);
  if (!empty($result) && !empty($result['country'])) {
    echo "GeoIP resolved successfully using protocol: $protocol\n";
    var_dump($result);
    $hasWorked = true;
    break;
  }
}
if (!$hasWorked) {
  $ip     = explode(':', $ipPort)[0];
  $result = GeoIpHelper::getGeoIpSimple($ip, $proxy_db);
  if (!empty($result) && !empty($result['country'])) {
    echo "GeoIP resolved successfully using simple method\n";
    var_dump($result);
    $hasWorked = true;
  }
}
if (!$hasWorked) {
  echo "Failed to resolve GeoIP for proxy: $ipPort\n";
}
