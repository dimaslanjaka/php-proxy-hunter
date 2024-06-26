<?php

// generate ports from IP

require_once __DIR__ . '/../func-proxy.php';

$parseData = parseQueryOrPostBody();

$ips = [];

if (!empty($parseData['ip'])) {
  $ips = extractIPs($parseData['ip']);
}

foreach ($ips as $ip) {
  if (isValidIp($ip)) {
    $outputPath = tmp() . '/ips-ports/' . $ip . '.txt';
    // skip generate IP:PORT when output file exist
    if (file_exists($outputPath)) {
      continue;
    }
    $proxies = generateIPWithPorts($ip);
    write_file($outputPath, implode(PHP_EOL, $proxies));
  }
}
