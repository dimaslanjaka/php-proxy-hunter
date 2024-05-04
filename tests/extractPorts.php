<?php

require_once __DIR__ . '/../func-proxy.php';

$dead = extractIpPortFromFile(__DIR__ . '/../dead.txt', true);
$untested = extractIpPortFromFile(__DIR__ . '/../proxies.txt', true);
$proxies = array_merge($dead, $untested);
$str = 'username:password@103.48.68.34:83
45.236.31.160:999
84.241.8.234:8080
84.241.8.234:8080@username:password
XXXXX192.198.1.100:80XXX
138.118.104.166:999
62.27.108.174:8080
45.4.253.133:999
103.47.175.161:83
201.220.150.89:999
185.26.197.13:8080
8.213.129.15:2
69.60.116.19:19772
67.43.236.20:26973
188.166.231.51:7497
hello 8.213.129.15:2 *x';

$extract = extractIpPorts($str);
var_dump($extract);


function get_ports()
{
  global $proxies;
  $ports = array_map(function ($proxy) {
    // Split the proxy string by ":" and get the port part
    $parts = explode(":", $proxy);
    return end($parts); // Get the last element of the array which is the port
  }, $proxies);

  $unique_ports = array_unique($ports);

  echo json_encode($unique_ports);
}
