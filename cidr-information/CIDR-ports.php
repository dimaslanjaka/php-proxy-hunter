<?php

// generate ports from generated ip ranges

require_once __DIR__ . '/../func-proxy.php';

// disallow web server access
if (php_sapi_name() !== 'cli') {
  // Redirect the user away or show an error message
  header('HTTP/1.1 403 Forbidden');
  die('Direct access not allowed');
}

// set memory
ini_set('memory_limit', '2024M');

$ipList = [];

$ips_folder = tmp() . '/ips';
if (file_exists($ips_folder)) {
  $filePath   = getRandomFileFromFolder($ips_folder, 'txt');
  $ipList     = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $outputPath = tmp() . '/ips-ports/' . basename($filePath);
  createParentFolders($outputPath);
  if (file_exists($outputPath)) {
    exit("cannot rewrite already generated proxy, please remove $outputPath");
  }
  if (empty($ipList)) {
    unlink($filePath);
    exit('ips empty');
  }
}

$commonPorts = [
  80, 81, 83, 88, 3128, 3129, 3654, 4444, 5800, 6588, 6666,
  6800, 7004, 8080, 8081, 8082, 8083, 8088, 8118, 8123, 8888,
  9000, 8084, 8085, 9999, 45454, 45554, 53281, 8443,
];

// extract ports from existing proxies
$dead     = extractIpPortFromFile(__DIR__ . '/../dead.txt', true);
$untested = extractIpPortFromFile(__DIR__ . '/../proxies.txt', true);
$proxies  = array_merge($dead, $untested);

$ports = array_map(function ($proxy) {
  // Split the proxy string by ":" and get the port part
  $parts = explode(':', $proxy);
  return end($parts); // Get the last element of the array which is the port
}, $proxies);

$commonPorts = array_unique(array_merge($commonPorts, $ports));

// Record the start time
$startTime = microtime(true);

foreach ($ipList as $ip) {
  // Check if execution time more than [n] seconds
  if (microtime(true) - $startTime > 120) {
    break; // Exit the loop
  }

  $ip = trim($ip);

  if (!isValidIp($ip)) {
    continue;
  }

  $ip_ports = array_map(function ($port) use ($ip) {
    $port = trim((string) $port);
    return "$ip:$port";
  }, $commonPorts);

  // write generated IP:PORT
  append_content_with_lock($outputPath, PHP_EOL . implode(PHP_EOL, $ip_ports) . PHP_EOL);
  // remove ip
  removeStringFromFile($filePath, trim($ip));

  // echo "scan ports $ip" . PHP_EOL;
  // scan common proxy ports
  // $proxies = scanArrayPorts($ip, $commonPorts);
  // scan all ports
  // $proxies = array_unique(array_merge($proxies, scanRangePorts($ip, 80, 65535)));
  // if (!empty($proxies)) {
  //   // remove checked ip from source
  //   removeStringFromFile($filePath, trim($ip));
  //   // write open IP:PORT into test files
  //   append_content_with_lock(__DIR__ . '/proxies.txt', PHP_EOL . implode(PHP_EOL, $proxies) . PHP_EOL);
  //   append_content_with_lock(__DIR__ . '/proxies-backup.txt', PHP_EOL . implode(PHP_EOL, $proxies) . PHP_EOL);
  // }
}

//rewriteIpPortFile($outputPath);

// rewriteIpPortFile(__DIR__ . '/proxies.txt');
