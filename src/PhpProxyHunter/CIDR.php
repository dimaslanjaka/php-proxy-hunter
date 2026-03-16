<?php

namespace PhpProxyHunter;

class CIDR
{
  public static function getIPRange(string $cidr): array
  {
    list($ip, $mask) = explode('/', trim($cidr));

    $ipLong   = ip2long($ip);
    $maskLong = ~((1 << (32 - $mask)) - 1);

    $start = $ipLong & $maskLong;
    $end   = $ipLong | (~$maskLong & 0xFFFFFFFF);

    $ips = [];
    for ($i = $start; $i <= $end; $i++) {
      $rangeIp = long2ip($i);
      if (is_string($rangeIp)) {
        $ips[] = trim($rangeIp);
      }
    }

    return $ips;
  }

  public static function IPv6CIDRToRange($cidr): array
  {
    list($ip, $prefix) = explode('/', $cidr);
    $rangeStart        = inet_pton($ip);
    $rangeEnd          = $rangeStart;

    if ($prefix < 128) {
      $suffix = 128 - $prefix;
      for ($i = 0; $i < $suffix; $i++) {
        $rangeStart[$i] = chr(ord($rangeStart[$i]) & (0xFF << ($i % 8)));
        $rangeEnd[$i]   = chr(ord($rangeEnd[$i]) | (0xFF >> (7 - $i % 8)));
      }
    }

    return [
      'start' => inet_ntop($rangeStart),
      'end'   => inet_ntop($rangeEnd),
    ];
  }

  public static function IPv6CIDRToList($cidr): array
  {
    $range = self::IPv6CIDRToRange($cidr);
    $start = inet_pton($range['start']);
    $end   = inet_pton($range['end']);
    $ips   = [];

    // Increment IP address in binary representation.
    while (strcmp($start, $end) <= 0) {
      $ips[] = inet_ntop($start);
      for ($i = strlen($start) - 1; $i >= 0; $i--) {
        $start[$i] = chr(ord($start[$i]) + 1);
        if ($start[$i] != chr(0)) {
          break;
        }
      }
    }

    return $ips;
  }

  /**
   * Generate a random IP address within a CIDR range.
   *
   * @param string $cidr The CIDR range (e.g., "192.168.1.0/24").
   * @return string The random IP address.
   */
  public static function generateRandomIP(string $cidr): string
  {
    list($ip, $subnet) = explode('/', $cidr);

    $ipBinary   = ip2long($ip);
    $subnetSize = pow(2, (32 - $subnet));

    // Exclude network and broadcast addresses.
    $offset = mt_rand(1, $subnetSize - 2);

    return long2ip($ipBinary + $offset);
  }

  /**
   * Generate a random port number.
   *
   * @return int The random port number.
   */
  public static function generateRandomPort(): int
  {
    return mt_rand(1024, 65535);
  }
}
