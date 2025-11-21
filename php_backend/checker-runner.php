<?php

require_once __DIR__ . '/shared.php';


/**
 * Minimal shared helpers for proxy checker scripts.
 * Extracted from check-http-proxy.php and check-https-proxy.php to
 * reduce duplication for logging, proxy loading and simple runner utilities.
 */
function get_log_file_shared(string $hashFilename): string {
  $_logFile = tmp() . "/logs/{$hashFilename}.txt";
  if (!file_exists($_logFile)) {
    file_put_contents($_logFile, '');
  }
  setMultiPermissions([$_logFile], true);
  return $_logFile;
}

function _log_shared(string $hashFilename, ...$args): void {
  global $isCli;
  $_logFile = get_log_file_shared($hashFilename);
  $message  = join(' ', $args) . PHP_EOL;

  append_content_with_lock($_logFile, $message);
  echo $message;
  if (!empty($isCli)) {
    // CLI behaviour: nothing special
  } else {
    flush();
  }
}

/**
   * Load proxies from $file or $proxy string (JSON or newline list) or from $proxy_db.
   * Mode is a string 'http' or 'https' to let caller apply simple filtering logic.
   * Returns raw content suitable to pass to the script's check() function.
   */
  function load_proxies_for_mode($file, $proxy, $mode, $proxy_db) {
    if (!$file && !$proxy) {
      // Merge working and untested proxies
      $proxiesDb = array_merge($proxy_db->getWorkingProxies(100), $proxy_db->getUntestedProxies(100));

      if ($mode === 'http') {
        // keep dead or non-SSL (we only want HTTP testing)
        $filteredArray = array_filter($proxiesDb, function ($item) {
          if (strtolower($item['status']) != 'active') {
            return true;
          }
          return strtolower($item['https']) != 'true';
        });
      } else {
        // https mode: keep non-active or non-https (slightly different in original)
        $filteredArray = array_filter($proxiesDb, function ($item) {
          if (strtolower($item['status']) != 'active') {
            return true;
          }
          return strtolower($item['https']) != 'true';
        });
      }

      $proxyArray = array_map(function ($item) {
        return $item['proxy'];
      }, $filteredArray);

      return json_encode($proxyArray);
    } elseif ($file) {
      $read = read_file($file);
      if ($read) {
        return $read;
      }
      return null;
    }

    return $proxy;
  }
