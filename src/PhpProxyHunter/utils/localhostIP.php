<?php

function isLocalOrPrivateIP(string $ip): bool {
  return isLocalhostIP($ip) || isPrivateIP($ip);
}

function isLocalhostIP(string $ip): bool {
  if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    return false;
  }

  // IPv4 loopback 127.0.0.0/8
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return (ip2long($ip) & ip2long('255.0.0.0')) === ip2long('127.0.0.0');
  }

  // IPv6 loopback
  return $ip === '::1';
}

function isPrivateIP(string $ip): bool {
  if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    return false;
  }

  // IPv4 private networks
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $long = ip2long($ip);

    // 10.0.0.0/8
    if (($long & ip2long('255.0.0.0')) === ip2long('10.0.0.0')) {
      return true;
    }

    // 172.16.0.0/12
    if (($long & ip2long('255.240.0.0')) === ip2long('172.16.0.0')) {
      return true;
    }

    // 192.168.0.0/16
    if (($long & ip2long('255.255.0.0')) === ip2long('192.168.0.0')) {
      return true;
    }

    return false;
  }

  // IPv6 Unique Local (fc00::/7)
  if (stripos($ip, 'fc') === 0 || stripos($ip, 'fd') === 0) {
    return true;
  }

  // IPv6 Link-local (fe80::/10)
  if (stripos($ip, 'fe8') === 0) {
    return true;
  }

  return false;
}
