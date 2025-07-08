<?php

function getIPRange(string $cidr): array
{
  list($ip, $mask) = explode('/', trim($cidr));

  $ipLong = ip2long($ip);
  $maskLong = ~((1 << (32 - $mask)) - 1);

  $start = $ipLong & $maskLong;
  $end = $ipLong | (~$maskLong & 0xFFFFFFFF);

  $ips = [];
  for ($i = $start; $i <= $end; $i++) {
    $ip = long2ip($i);
    if (is_string($ip)) {
      $ips[] = trim($ip);
    }
  }

  return $ips;
}

// Example usage
// $cidr = "159.21.130.0/24";
// $ipList = getIPRange($cidr);

// foreach ($ipList as $ip) {
//   echo $ip . "\n";
// }

function IPv6CIDRToRange($cidr): array
{
  list($ip, $prefix) = explode('/', $cidr);
  $range_start = inet_pton($ip);
  $range_end = $range_start;

  if ($prefix < 128) {
    $suffix = 128 - $prefix;
    for ($i = 0; $i < $suffix; $i++) {
      $range_start[$i] = chr(ord($range_start[$i]) & (0xFF << ($i % 8)));
      $range_end[$i] = chr(ord($range_end[$i]) | (0xFF >> (7 - $i % 8)));
    }
  }

  return [
    'start' => inet_ntop($range_start),
    'end' => inet_ntop($range_end)
  ];
}

// function IPv6CIDRToList($cidr)
// {
//   $range = IPv6CIDRToRange($cidr);
//   $start = inet_pton($range['start']);
//   $end = inet_pton($range['end']);
//   $ips = array();
//   while (strcmp($start, $end) <= 0) {
//     $ips[] = inet_ntop($start);
//     $start = gmp_add($start, 1);
//   }
//   return $ips;
// }

function IPv6CIDRToList($cidr): array
{
  $range = IPv6CIDRToRange($cidr);
  $start = inet_pton($range['start']);
  $end = inet_pton($range['end']);
  $ips = [];

  // Increment IP address in binary representation
  while (strcmp($start, $end) <= 0) {
    $ips[] = inet_ntop($start);
    // Increment binary representation of IP address
    for ($i = strlen($start) - 1; $i >= 0; $i--) {
      $start[$i] = chr(ord($start[$i]) + 1);
      if ($start[$i] != chr(0)) {
        break;
      }
    }
  }
  return $ips;
}

// Example usage
// $cidr = '2404:6800:4000::/36';
// $ips = IPv6CIDRToList($cidr);
// foreach ($ips as $ip) {
//   echo "$ip\n";
// }


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
