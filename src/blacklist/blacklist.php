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
  $path = !empty($blacklistConf) ? $blacklistConf : __DIR__ . '/../../data/blacklist.conf';
  if (!is_string($path) || !is_file($path) || !is_readable($path)) {
    return [];
  }

  $maxCidrExpansion = 65536;
  $results          = [];
  $handle           = fopen($path, 'rb');
  if ($handle === false) {
    return [];
  }

  while (($line = fgets($handle)) !== false) {
    $line = trim($line);
    if (empty($line) || strpos($line, '#') === 0) {
      continue;
    }

    // check if line is CIDR notation
    if (strpos($line, '/') !== false) {
      // Only expand IPv4 CIDR here; IPv6 CIDR expansion can be too large.
      if (preg_match('/^(?:\d{1,3}\.){3}\d{1,3}\/(?:[0-9]|[12][0-9]|3[0-2])$/', $line) === 1) {
        $cidrParts = explode('/', $line, 2);
        if (!is_array($cidrParts) || count($cidrParts) !== 2) {
          continue;
        }

        $cidrIp   = trim($cidrParts[0]);
        $cidrMask = trim($cidrParts[1]);
        if (!filter_var($cidrIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
          continue;
        }
        if (!is_numeric($cidrMask)) {
          continue;
        }

        $cidrMaskInt = (int) $cidrMask;
        if ($cidrMaskInt < 0 || $cidrMaskInt > 32) {
          continue;
        }

        $cidrExpansionSize = (int) pow(2, (32 - $cidrMaskInt));
        if ($cidrExpansionSize > $maxCidrExpansion) {
          continue;
        }

        $packedIp = inet_pton($cidrIp);
        if ($packedIp !== false) {
          $unpacked = unpack('N', $packedIp);
          if (is_array($unpacked) && isset($unpacked[1])) {
            $ipInt = (int) $unpacked[1];
            $mask  = $cidrMaskInt;

            $maskInt = $mask === 0 ? 0 : ((0xFFFFFFFF << (32 - $mask)) & 0xFFFFFFFF);
            $start   = $ipInt & $maskInt;
            $end     = $start | (~$maskInt & 0xFFFFFFFF);

            for ($current = $start; $current <= $end; $current++) {
              $ip = long2ip((int) $current);
              if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $results[$ip] = true;
              }
              if ($current === 0xFFFFFFFF) {
                break;
              }
            }
          }
        }
      }
    } else {
      // check if line is a valid IP
      if (filter_var($line, FILTER_VALIDATE_IP)) {
        $results[$line] = true;
      }
    }
  }
  fclose($handle);

  return array_keys($results);
}

/**
 * Check if a proxy (IP or IP:PORT) is blacklisted.
 *
 * Accepts strings like `IP`, `IP:PORT`, `[IPv6]:PORT`, or raw IPv6.
 * Extracts the IP portion and checks it against the blacklist entries.
 *
 * @param string $proxy Proxy string (IP or IP:PORT or [IPv6]:PORT)
 * @param string|null $blacklistConf Optional path to a blacklist configuration file
 * @return bool True if the extracted IP is present in the blacklist
 */
function is_blacklist($proxy, $blacklistConf = null)
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

  $blacklist = get_blacklist($blacklistConf);
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
