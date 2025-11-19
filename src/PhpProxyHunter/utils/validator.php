<?php

/** @noinspection RegExpRedundantEscape */
/** @noinspection RegExpUnnecessaryNonCapturingGroup */

/**
 * Validates a proxy string.
 *
 * @param string|null $proxy The proxy string to validate.
 * @param bool $validate_credential Whether to validate credentials if present.
 * @return bool True if the proxy is valid, false otherwise.
 */
function isValidProxy($proxy, $validate_credential = false) {
  if (empty($proxy)) {
    return false;
  }

  $username      = $password      = null;
  $hasCredential = strpos($proxy, '@') !== false;

  // Extract username and password if credentials are present
  if ($hasCredential) {
    list($proxy, $credential)  = explode('@', trim($proxy), 2);
    list($username, $password) = explode(':', trim($credential), 2);
  }

  // Extract IP address and port
  $parts = explode(':', trim($proxy), 2);

  $ip   = trim($parts[0]);
  $port = isset($parts[1]) ? trim($parts[1]) : null;

  if (!$port) {
    return false;
  }

  // Validate IP address
  $is_ip_valid = filter_var($ip, FILTER_VALIDATE_IP) !== false && strlen($ip) >= 7 && strpos($ip, '..') === false;

  // Validate port number
  $is_port_valid = strlen($port) >= 2 && filter_var($port, FILTER_VALIDATE_INT, [
    'options' => [
      'min_range' => 1,
      'max_range' => 65535,
    ],
  ]);

  // Check if proxy is valid
  $proxyLength    = strlen($proxy);
  $re             = '/(?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:(?!0)\d{2,5}/';
  $is_proxy_valid = $is_ip_valid && $is_port_valid && $proxyLength >= 10 && $proxyLength <= 21 && preg_match($re, $proxy);

  // Validate credentials if required
  if ($hasCredential && $validate_credential) {
    return $is_proxy_valid && !empty($username) && !empty($password);
  }

  return $is_proxy_valid;
}

/**
 * Validate a given proxy IP address.
 *
 * @param mixed $proxy The proxy IP address to validate. Can be null.
 * @return bool True if the proxy IP address is valid, false otherwise.
 */
function isValidIp($proxy) {
  if (!$proxy) {
    return false;
  }

  $split       = explode(':', trim($proxy), 2);
  $ip          = $split[0];
  $is_ip_valid = filter_var($ip, FILTER_VALIDATE_IP) !== false
    && strlen($ip) >= 7
    && strpos($ip, '..') === false;
  $re = '/(?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/';

  return $is_ip_valid && preg_match($re, $ip);
}

/**
 * Check if a port is open on a given IP address.
 *
 * @param string $proxy The IP address and port to check in the format "IP:port".
 * @param int $timeout The timeout value in seconds (default is 10 seconds).
 * @return bool True if the port is open, false otherwise.
 */
function isPortOpen($proxy, $timeout = 10) {
  $proxy = trim((string)$proxy);

  // disallow empty proxy
  if (empty($proxy) || strlen($proxy) < 7) {
    return false;
  }

  // Separate IP and port
  list($ip, $port) = explode(':', $proxy);

  // Create a TCP/IP socket with the specified timeout
  $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

  // Check if the socket could be opened
  if ($socket === false) {
    return false; // Port is closed
  } else {
    fclose($socket);
    return true; // Port is open
  }
}
