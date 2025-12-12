<?php

require_once __DIR__ . '/../func-proxy.php';

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

$db = new ProxyDB(__DIR__ . '/../tmp/database.sqlite');

$dead     = extractIpPortFromFile(__DIR__ . '/../dead.txt', true);
$untested = extractIpPortFromFile(__DIR__ . '/../proxies.txt', true);
$proxies  = array_merge($dead, $untested);
$str      = '
username1:password1@1.1.1.1:83
1.1.1.1:8080@username2:password2
XXXXX192.198.1.100:80XXX
138.118.104.166:999
45.4.253.133:999
185.26.197.13:8080
8.213.129.15:2
hello 8.213.129.15:2 *x
185.26.197.13 80
#1 185.26.196.13 803
185.26.097.13 8080

"_id":"660c243d6fb9cbee37776b30","ip":"31.210.134.114","anonymityLevel":"elite","asn":"AS39537","city":"London","country":"GB","created_at":"2024-04-02T15:29:01.811Z","google":false,"isp":"SysGroup plc","lastChecked":1720124562,"latency":8.94,"org":"Hub Network Services Ltd","port":"13080",
"_id":"631a9b962fb0f02dd5a784f1","ip":"212.83.136.242","anonymityLevel":"elite","asn":"AS12876","city":"Vitry-sur-Seine","country":"FR","created_at":"2022-09-09T01:49:10.094Z","google":false,"isp":"Online S.A.S.","lastChecked":1720124562,"latency":0.778,"org":"ONLINE","port":"5836","protocols":["socks4"],"region":null,"responseTime":1616,"speed":2,"updated_at":"2024-07-04T20:22:42.096Z","workingPercent":null,"upTime":99.94607710973308,"upTimeSuccessCount":7414,"upTimeTryCount":7418},{"_id":"630eb9c2de95dae5212ea404","ip":"172.64.130.68","anonymityLevel":"elite","asn":"AS13335","city":"Newark","country":"US","created_at":"2022-08-31T01:30:42.871Z","google":false,"isp":"Cloudflare, Inc.","lastChecked":1720124562,"latency":2.621,"org":"Cloudflare, Inc.","port":"13335","protocols":["socks4"],"region":null,"responseTime":3892,"speed":2,"updated_at":"2024-07-04T20:22:42.093Z","workingPercent":null,"upTime":99.97319394183086,"upTimeSuccessCount":7459,"upTimeTryCount":7461},{"_id":"660526f56fb9cbee3795984a","ip":"88.245.138.87","anonymityLevel":"elite","asn":"AS9121","city":"Istanbul","country":"TR","created_at":"2024-03-28T08:14:45.852Z","google":false,"isp":"Turk Telekomunikasyon A.S","lastChecked":1720124562,"latency":87.44,"org":"TurkTelecom","port":"1080","prot
';

$extract = extractProxies($str, $db, false);
// var_dump($extract[0]);
$format = array_map(function (Proxy $item) {
  if (!is_null($item->username) && !is_null($item->password)) {
    // var_dump($item->proxy);
    return $item->proxy . '@' . $item->username . ':' . $item->password;
  }
  return $item->proxy;
}, $extract);
echo implode(PHP_EOL, $format);
echo PHP_EOL . 'Total extracted proxies: ' . count($extract) . PHP_EOL;
echo 'Transform class Proxy as JSON:' . PHP_EOL;
echo $extract[0]->toJson(true, true) . PHP_EOL;
echo 'Transform class Proxy as text:' . PHP_EOL;
echo (string) $extract[0] . PHP_EOL;


function get_ports() {
  global $proxies;
  $ports = array_map(function ($proxy) {
    // Split the proxy string by ":" and get the port part
    $parts = explode(':', $proxy);
    return end($parts);
  // Get the last element of the array which is the port
  }, $proxies);

  $unique_ports = array_unique($ports);

  echo json_encode($unique_ports);
}
