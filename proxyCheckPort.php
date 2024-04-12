<?php

// Specify the file path
$filePath = __DIR__ . "/CIDR.txt";

// Read lines of the file into an array
$lines = file($filePath, FILE_IGNORE_NEW_LINES);

// Shuffle the array
shuffle($lines);

$ipList = [];

$ips = __DIR__ . '/ips';
if (!file_exists($ips)) mkdir($ips, 0777, true);

// foreach ($lines as $line) {
//   // $ipList = array_unique(array_merge($ipList, getIPRange($line)));
//   // $ipList = array_merge($ipList, getIPRange($line));
//   $cidrRangeFile = $ips . "/" . sanitizeFilename($line) . ".txt";
//   if (!file_exists($cidrRangeFile)) file_put_contents($cidrRangeFile, join("\n", getIPRange($line)));
// }

// get shuffled CIDR
$randomKey = array_rand($lines);
$randomItem = $lines[$randomKey];
$ipList = array_unique(array_merge($ipList, getIPRange($randomItem)));
$commonPorts = [
  80, 81, 83, 88, 3128, 3129, 3654, 4444, 5800, 6588, 6666,
  6800, 7004, 8080, 8081, 8082, 8083, 8088, 8118, 8123, 8888,
  9000, 8084, 8085, 9999, 45454, 45554, 53281
];

foreach ($ipList as $ip) {
  // echo "scan ports $ip";
  // scanRangePorts($ip, 80, 88);
  scanArrayPorts($ip, $commonPorts);
}

function scanRangePorts(string $ip, int $startPort = 1, int $endPort = 65535)
{
  for ($port = $startPort; $port <= $endPort; $port++) {
    scanPort($ip, $port);
  }
}

function scanArrayPorts(string $ip, array $ports)
{
  foreach ($ports as $port) {
    scanPort($ip, $port);
  }
}

function scanPort(string $ip, int $port)
{
  echo "scan port $ip:$port\n";
  $connection = @fsockopen($ip, $port, $errno, $errstr, 1);
  if (is_resource($connection)) {
    echo "Port $port is open.\n";
    fclose($connection);
  }
}

function sanitizeFilename($filename)
{
  // Remove any character that is not alphanumeric, underscore, dash, or period
  $filename = preg_replace("/[^\w\-\. ]/", '-', $filename);

  return $filename;
}

// $ip = "127.0.0.1"; // Change this to the IP you want to scan
// $startPort = 1; // Starting port number
// $endPort = 65535; // Ending port number

// scanPorts($ip, $startPort, $endPort);
