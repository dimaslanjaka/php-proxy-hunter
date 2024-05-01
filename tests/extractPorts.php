<?php

require_once __DIR__ . '/../func.php';

$dead = extractIpPortFromFile(__DIR__ . '/../dead.txt', true);
$untested = extractIpPortFromFile(__DIR__ . '/../proxies.txt', true);
$proxies = array_merge($dead, $untested);

$ports = array_map(function ($proxy) {
  // Split the proxy string by ":" and get the port part
  $parts = explode(":", $proxy);
  return end($parts); // Get the last element of the array which is the port
}, $proxies);

$unique_ports = array_unique($ports);

echo json_encode($unique_ports);
