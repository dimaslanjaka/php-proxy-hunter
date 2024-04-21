<?php

// scan ports from generated ip ranges

require_once __DIR__ . '/func.php';

$filePath = getRandomFileFromFolder(__DIR__ . '/tmp/ips', 'txt');
$ipList = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$commonPorts = [
  80, 81, 83, 88, 3128, 3129, 3654, 4444, 5800, 6588, 6666,
  6800, 7004, 8080, 8081, 8082, 8083, 8088, 8118, 8123, 8888,
  9000, 8084, 8085, 9999, 45454, 45554, 53281, 8443
];

// extract ports from existing proxies
$dead = extractIpPortFromFile(__DIR__ . '/dead.txt', true);
$untested = extractIpPortFromFile(__DIR__ . '/proxies.txt', true);
$proxies = array_merge($dead, $untested);

$ports = array_map(function ($proxy) {
  // Split the proxy string by ":" and get the port part
  $parts = explode(":", $proxy);
  return end($parts); // Get the last element of the array which is the port
}, $proxies);

$commonPorts = array_unique(array_merge($commonPorts, $ports));

// Record the start time
$startTime = microtime(true);

foreach ($ipList as $ip) {
  // Check if more than 120 seconds have passed
  if (microtime(true) - $startTime > 120) {
    break; // Exit the loop
  }

  $ip = trim($ip);
  echo "scan ports $ip" . PHP_EOL;
  // scan common proxy ports
  $proxies = scanArrayPorts($ip, $commonPorts);
  // scan all ports
  // $proxies = array_unique(array_merge($proxies, scanRangePorts($ip, 80, 65535)));
  if (!empty($proxies)) {
    // remove checked ip from source
    removeStringFromFile($filePath, trim($ip));
    // write open IP:PORT into test files
    append_content_with_lock(__DIR__ . '/proxies.txt', PHP_EOL . implode(PHP_EOL, $proxies) . PHP_EOL);
    append_content_with_lock(__DIR__ . '/proxies-backup.txt', PHP_EOL . implode(PHP_EOL, $proxies) . PHP_EOL);
  }
}

rewriteIpPortFile(__DIR__ . '/proxies.txt');

/**
 * Scans a range of ports on a given IP address and returns an array of proxies.
 *
 * @param string $ip The IP address to scan ports on.
 * @param int $startPort The starting port of the range (default is 1).
 * @param int $endPort The ending port of the range (default is 65535).
 * @return array An array containing the proxies found during scanning.
 */
function scanRangePorts(string $ip, int $startPort = 1, int $endPort = 65535)
{
  $proxies = [];
  for ($port = $startPort; $port <= $endPort; $port++) {
    if (scanPort($ip, $port)) {
      $proxies[] = "$ip:$port";
    }
  }
  return $proxies;
}

/**
 * Scans an array of specific ports on a given IP address and returns an array of proxies.
 *
 * @param string $ip The IP address to scan ports on.
 * @param array $ports An array containing the ports to scan.
 * @return array An array containing the proxies found during scanning.
 */
function scanArrayPorts(string $ip, array $ports)
{
  $proxies = [];
  foreach ($ports as $port) {
    if (scanPort($ip, $port)) {
      $proxies[] = "$ip:$port";
    }
  }
  return $proxies;
}

/**
 * Scans a specific port on a given IP address.
 *
 * @param string $ip The IP address to scan the port on.
 * @param int $port The port to scan.
 * @return bool Returns true if the port is open, false otherwise.
 */
function scanPort(string $ip, int $port)
{
  $ip = trim($ip);
  echo "Scanning port $ip:$port\n";
  $connection = @fsockopen($ip, $port, $errno, $errstr, 10);
  if (is_resource($connection)) {
    echo "Port $port is open.\n";
    fclose($connection);
    return true;
  }
  return false;
}

// $ip = "127.0.0.1"; // Change this to the IP you want to scan
// $startPort = 1; // Starting port number
// $endPort = 65535; // Ending port number

// scanPorts($ip, $startPort, $endPort);

/**
 * Get a random file from a folder.
 *
 * @param string $folder The path to the folder containing files.
 * @param string|null $file_extension The optional file extension without dot (.) to filter files by.
 * @return string|null The name of the randomly selected file, or null if no file found with the specified extension.
 */
function getRandomFileFromFolder($folder, $file_extension = null)
{
  // Get list of files in the folder
  $files = scandir($folder);

  // Remove special directories "." and ".." from the list
  $files = array_diff($files, array('.', '..'));

  // Filter files by extension if provided
  if ($file_extension !== null) {
    $files = array_filter($files, function ($file) use ($file_extension) {
      return pathinfo($file, PATHINFO_EXTENSION) == $file_extension;
    });
  }

  // Get number of files
  $num_files = count($files);

  // Check if there are files with the specified extension
  if ($num_files === 0) {
    return null; // No files found with the specified extension
  }

  // Generate a random index
  $random_index = mt_rand(0, $num_files - 1);

  // Get the randomly selected file
  $random_file = $files[$random_index];

  return $folder . '/' . $random_file;
}
