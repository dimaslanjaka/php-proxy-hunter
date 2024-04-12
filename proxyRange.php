<?php

// Specify the file path
$filePath = __DIR__ . "/CIDR.txt";

// Read lines of the file into an array
$lines = file($filePath, FILE_IGNORE_NEW_LINES);

// Shuffle the array
shuffle($lines);

$file = __DIR__ . '/proxyRange.txt';
$fileHandle = fopen($file, 'a');

// Loop through each CIDR, get IP ranges, and append them directly to the file
foreach ($lines as $cidr) {
  $arrayIp = [];
  if (strpos($cidr, ":") !== false) {
    // $arrayIp = IPv6CIDRToList($cidr);
  } else {
    $arrayIp = getIPRange($cidr);
  }
  foreach ($arrayIp as $ip) {
    fwrite($fileHandle, $ip . PHP_EOL);
  }
}

// Close the file handle
fclose($fileHandle);

// Read the content of the file, make it unique, and remove empty lines
$content = file_get_contents($file);
$lines = explode(PHP_EOL, $content);
$lines = array_unique(array_filter($lines));

// Write back the unique content to the file
file_put_contents($file, implode("\n", $lines));

function getIPRange(string $cidr)
{
  list($ip, $mask) = explode('/', trim($cidr));

  $ipLong = ip2long($ip);
  $maskLong = ~((1 << (32 - $mask)) - 1);

  $start = $ipLong & $maskLong;
  $end = $ipLong | (~$maskLong & 0xFFFFFFFF);

  $ips = array();
  for ($i = $start; $i <= $end; $i++) {
    $ips[] = long2ip($i);
  }

  return $ips;
}

// Example usage
// $cidr = "159.21.130.0/24";
// $ipList = getIPRange($cidr);

// foreach ($ipList as $ip) {
//   echo $ip . "\n";
// }

function IPv6CIDRToRange($cidr)
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

  return array(
    'start' => inet_ntop($range_start),
    'end' => inet_ntop($range_end)
  );
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

function IPv6CIDRToList($cidr)
{
  $range = IPv6CIDRToRange($cidr);
  $start = inet_pton($range['start']);
  $end = inet_pton($range['end']);
  $ips = array();

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
