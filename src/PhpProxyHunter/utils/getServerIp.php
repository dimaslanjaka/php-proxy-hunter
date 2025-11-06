<?php

/**
 * Get the IP address of the server.
 *
 * This function attempts to retrieve the server's IP address using both PHP's
 * built-in global variables and system commands, ensuring compatibility with
 * both Linux and Windows operating systems.
 * If successful, it saves the IP address to a file. If the file already
 * exists and contains an IP address, it loads the IP address from the file.
 *
 * @return string|false The IP address as a string if found, or false if not found.
 */
function getServerIp() {
  $filePath = get_project_root() . '/tmp/locks/server-ip.txt';
  $result   = false;

  // Delete cached IP file if it's older than 5 mins on debug devices
  if (is_debug_device() && file_exists($filePath) && (time() - filemtime($filePath) > 300)) {
    unlink($filePath);
  }

  // Try to load IP from file if it exists and is not empty
  if (file_exists($filePath) && filesize($filePath) > 0) {
    $ipFromFile = trim(file_get_contents($filePath));
    if (!empty($ipFromFile)) {
      $result = $ipFromFile;
    }
  }

  // Check for server address
  if (empty($result) && !empty($_SERVER['SERVER_ADDR'])) {
    $serverIp = $_SERVER['SERVER_ADDR'];
    file_put_contents($filePath, $serverIp);
    $result = $serverIp;
  }

  // If the above fails, try to get the IP address from the system
  if (empty($result) && PHP_OS_FAMILY === 'Windows') {
    // Get the output from ipconfig and filter out IPv4 addresses
    $output = shell_exec('ipconfig');
    if ($output) {
      // Use regex to find all IPv4 addresses in the output
      preg_match_all('/IPv4 Address[^\d]*([\d\.]+)/i', $output, $matches);
      if (!empty($matches[1][0])) {
        $serverIp = trim($matches[1][0]);
        write_file($filePath, $serverIp);
        $result = $serverIp;
      }
    }
  } elseif (empty($result)) {
    // For Linux, use hostname -I and filter out IPv6 addresses
    $ip = trim(shell_exec('hostname -I'));
    if ($ip) {
      // Split the result and find the first valid IPv4 address
      $ipParts = explode(' ', $ip);
      foreach ($ipParts as $part) {
        if (filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
          $serverIp = trim($part);
          file_put_contents($filePath, $serverIp);
          $result = $serverIp;
        }
      }
    }
  }

  $isCurrentIpIsLocal = !empty($result) && preg_match('/^(192\.168|127\.)/', $result) === 1;
  if ($isCurrentIpIsLocal) {
    // Try to get public IP if current IP is a common router IP
    $external_ip = getPublicIP(false, 10);
    if ($external_ip !== false && filter_var($external_ip, FILTER_VALIDATE_IP)) {
      $result = $external_ip;
    }
  }

  return $result;
}
