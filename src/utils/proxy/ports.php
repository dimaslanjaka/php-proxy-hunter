<?php

/**
 * Scans a range of ports on a given IP address and returns an array of proxies.
 *
 * @param string $ip The IP address to scan ports on.
 * @param int $startPort The starting port of the range (default is 1).
 * @param int $endPort The ending port of the range (default is 65535).
 * @return array An array containing the proxies found during scanning.
 */
function scanRangePorts(string $ip, int $startPort = 1, int $endPort = 65535): array
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
function scanArrayPorts(string $ip, array $ports): array
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
function scanPort(string $ip, int $port): bool
{
  $ip = trim($ip);
  echo "Scanning port $ip:$port\n";
  $connection = @fsockopen($ip, $port, $errno, $error_string, 10);
  if (is_resource($connection)) {
    echo "Port $port is open.\n";
    fclose($connection);
    return true;
  }
  return false;
}
