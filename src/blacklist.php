<?php

/**
 * Load blacklist IPs from a blacklist configuration file.
 *
 * Uses `read_file()` and `extractIPs()` helpers. Returns an array of IPs
 * (empty array if the file is missing or contains no IPs).
 *
 * @param string|null $blacklistConf Optional path to a blacklist configuration file
 * @return array Array of IP strings
 */
function get_blacklist($blacklistConf = null)
{
  $path        = !empty($blacklistConf) ? $blacklistConf : __DIR__ . '/../data/blacklist.conf';
  $r_blacklist = read_file($path);
  if (!$r_blacklist) {
    return [];
  }
  $blacklist = extractIPs($r_blacklist);
  if (!is_array($blacklist)) {
    return [];
  }
  return $blacklist;
}

/**
 * Check if a proxy (IP or IP:PORT) is blacklisted.
 *
 * Accepts strings like `IP`, `IP:PORT`, `[IPv6]:PORT`, or raw IPv6.
 * Extracts the IP portion and checks it against the blacklist entries.
 *
 * @param string $proxy Proxy string (IP or IP:PORT or [IPv6]:PORT)
 * @return bool True if the extracted IP is present in the blacklist
 */
function is_blacklist($proxy)
{
  if (empty($proxy) || !is_string($proxy)) {
    return false;
  }
  $p  = trim($proxy);
  $ip = null;

  // IPv6 in brackets: [::1]:8080
  if (preg_match('/^\[([^\]]+)\](?::\d+)?$/', $p, $m)) {
    $ip = $m[1];
  } else {
    // If whole string is a valid IP (v4 or v6)
    if (filter_var($p, FILTER_VALIDATE_IP)) {
      $ip = $p;
    } elseif (preg_match('/^(.+):\d+$/', $p, $m2)) {
      // Looks like IP:PORT (handles IPv6:PORT when IPv6 is not bracketed)
      $candidate = $m2[1];
      if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) || filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ip = $candidate;
      }
    }
    // Fallback: extract first IPv4 occurrence
    if (empty($ip) && preg_match('/(\d{1,3}(?:\.\d{1,3}){3})/', $p, $m3)) {
      $ip = $m3[1];
    }
  }

  if (empty($ip)) {
    return false;
  }

  $blacklist = get_blacklist(null);
  if (empty($blacklist) || !is_array($blacklist)) {
    return false;
  }
  return in_array($ip, $blacklist, true);
}

/**
 * Remove blacklisted IPs from the proxies database.
 *
 * This function delegates loading of blacklist entries to `get_blacklist()` and
 * deletes rows from `proxies` where `proxy` contains a blacklisted IP and
 * `status` is not `active`. Supports PDO drivers `sqlite` and `mysql`.
 *
 * @param PDO        $pdo           PDO instance connected to the proxies database
 * @param string|null $blacklistConf Optional path to a blacklist configuration file
 * @return void
 */
function blacklist_remover($pdo, $blacklistConf = null)
{
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  if (!in_array($driver, ['sqlite', 'mysql'])) {
    echo "[BLACKLIST] Unsupported database driver: $driver. Skipping blacklist removal.\n";
    return;
  }

  $blacklist = get_blacklist($blacklistConf);
  if (empty($blacklist)) {
    return;
  }

  foreach ($blacklist as $ip) {
    $query = 'DELETE FROM proxies WHERE proxy LIKE :proxy AND status != :active_status';
    $stmt  = $pdo->prepare($query);

    $proxy        = "%$ip%";
    $activeStatus = 'active';
    $stmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
    $stmt->bindParam(':active_status', $activeStatus, PDO::PARAM_STR);

    if ($stmt->execute()) {
      $affectedRows = $stmt->rowCount();
      echo "[BLACKLIST] $ip deleted $affectedRows row(s).\n";
    } else {
      $err = $stmt->errorInfo();
      echo "[BLACKLIST] $ip failed to delete rows: " . json_encode($err) . "\n";
    }
  }
}
