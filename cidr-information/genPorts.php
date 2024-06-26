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

function generateIPWithPorts($ip, $maxPort = 65535)
{
  // Initialize an empty array to hold the IP:PORT values
  $ipPorts = [];

  // Loop from port 80 to the maximum port value
  for ($port = 80; $port <= $maxPort; $port++) {
    // Add the IP:PORT value to the array
    $ipPorts[] = $ip . ':' . $port;
  }

  return $ipPorts;
}

function extractIPs($string)
{
  // Regular expression to match an IP address
  $ipPattern = '/\b(?:\d{1,3}\.){3}\d{1,3}\b/';

  // Use preg_match_all to find all IP addresses in the string
  if (preg_match_all($ipPattern, $string, $matches)) {
    return $matches[0]; // Return all matched IP addresses
  } else {
    return []; // Return empty array if no IP addresses are found
  }
}
