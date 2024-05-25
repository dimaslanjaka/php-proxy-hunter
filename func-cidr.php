<?php

require __DIR__ . '/func-proxy.php';

/**
 * Generate a random IP address within a CIDR range.
 *
 * @param string $cidr The CIDR range (e.g., "192.168.1.0/24").
 * @return string The random IP address.
 */
function generateRandomIP(string $cidr): string
{
  list($ip, $subnet) = explode('/', $cidr);

  // Convert IP to binary format
  $ipBinary = ip2long($ip);

  // Calculate the number of available IP addresses in the subnet
  $subnetSize = pow(2, (32 - $subnet));

  // Generate a random offset within the subnet
  $offset = mt_rand(1, $subnetSize - 2); // Exclude network address and broadcast address

  // Calculate the resulting IP
  return long2ip($ipBinary + $offset);
}

/**
 * Generate a random port number.
 *
 * @return int The random port number.
 */
function generateRandomPort(): int
{
  return mt_rand(1024, 65535);
}
