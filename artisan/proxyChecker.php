<?php

// CLI Proxy Checker
// Usage: php artisan/proxyChecker.php --proxy "IP:PORT" or "IP:PORT@user:pass"

require_once __DIR__ . '/../php_backend/shared.php';
require_once __DIR__ . '/../func-proxy.php';

use PhpProxyHunter\ProxyDB;

if (php_sapi_name() !== 'cli') {
  echo 'This script is intended to be run from CLI only.' . PHP_EOL;
  exit(1);
}

$short_opts = '';
$long_opts  = ['proxy:', 'lockFile:', 'admin:'];
$opts       = getopt($short_opts, $long_opts);
$isAdmin    = !empty($opts['admin']) && $opts['admin'] === 'true';

// Check for lock file to prevent concurrent runs for the same proxy
if (file_exists($opts['lockFile'] ?? '') && !$isAdmin) {
  echo 'Another instance is already running for this proxy. Exiting.' . PHP_EOL;
  exit(5);
}

if (empty($opts['proxy'])) {
  echo 'Missing --proxy argument. Example: --proxy "1.2.3.4:8080" or --proxy "1.2.3.4:8080@user:pass"' . PHP_EOL;
  exit(2);
}

$raw = trim($opts['proxy']);

// parse auth if present
$username = null;
$password = null;
$proxyStr = $raw;
if (str_contains($raw, '@')) {
  [$proxyStr, $auth] = explode('@', $raw, 2);
  if (str_contains($auth, ':')) {
    [$username, $password] = explode(':', $auth, 2);
  } else {
    $username = $auth;
  }
}

$proxyStr = trim($proxyStr);

// Validate IP:PORT basic
if (!preg_match('/^\d{1,3}(?:\.\d{1,3}){3}:\d{1,5}$/', $proxyStr)) {
  echo 'Proxy must be in form IP:PORT' . PHP_EOL;
  exit(3);
}

/**
 * Types to try. Order matters: prefer http then socks.
 */
$types = ['http', 'socks4', 'socks5', 'socks4a', 'socks5h'];

global $proxy_db;
if (!isset($proxy_db) || !$proxy_db instanceof ProxyDB) {
  echo 'Proxy DB not available.' . PHP_EOL;
  exit(4);
}

// Write lock file to prevent concurrent runs for the same proxy
if (!empty($opts['lockFile'])) {
  write_file($opts['lockFile'], (string)getmypid());
}

echo "Checking proxy: $raw" . PHP_EOL;

$results = [];

// Aggregation helpers
$working_types        = [];
$working_latencies    = [];
$anonymities          = [];
$tested_endpoints_agg = [];
$private_flags        = [];

foreach ($types as $type) {
  echo "- testing type: $type" . PHP_EOL;
  $tryEndpoints = ['https://www.example.com', 'http://httpforever.com/'];
  $r            = null;
  foreach ($tryEndpoints as $endpoint) {
    echo "  -> trying endpoint $endpoint" . PHP_EOL;
    try {
      $res = checkProxy($proxyStr, $type, $endpoint, [], $username, $password, false);
      if (is_array($res) && isset($res[0]) && is_array($res[0]) && isset($res[0]['result'])) {
        // multi result, pick first successful
        $found = null;
        foreach ($res as $cand) {
          if (!empty($cand['result'])) {
            $found = $cand;
            break;
          }
        }
        $r = $found ?? $res[0];
      } else {
        $r = $res;
      }
    } catch (Throwable $e) {
      $r = ['result' => false, 'error' => $e->getMessage()];
    }
    // if result true and not private, accept
    if (!empty($r['result']) && empty($r['private'])) {
      // record which endpoint used
      $r['tested_endpoint'] = $endpoint;
      break;
    }
  }

  $ok              = !empty($r['result']);
  $status          = $ok ? 'active' : (isset($r['error']) && str_contains((string)$r['error'], 'port') ? 'port-closed' : 'dead');
  $latency         = $r['latency'] ?? ($r['lat'] ?? null);
  $private         = isset($r['private']) ? ($r['private'] ? 'true' : 'false') : 'false';
  $tested_endpoint = $r['tested_endpoint'] ?? null;

  $results[$type] = [
    'result'  => $ok,
    'status'  => $status,
    'latency' => $latency,
    'error'   => $r['error'] ?? null,
    'https'   => $r['https'] ?? null,
    'private' => $private,
  ];

  // If working, collect info but don't break — test all protocols
  if ($ok) {
    $working_types[] = $type;
    if (is_numeric($latency)) {
      $working_latencies[] = (int)$latency;
    }
    $private_flags[] = ($private === 'true');
    if (!empty($tested_endpoint)) {
      $tested_endpoints_agg[] = $tested_endpoint;
    } elseif (!empty($r['https'])) {
      $tested_endpoints_agg[] = $r['https'] ? 'https' : 'http';
    }

    // try get anonymity (collect)
    try {
      $an = get_anonymity($proxyStr, $type, $username, $password);
      if (!empty($an)) {
        $anonymities[] = strtolower($an);
      }
    } catch (Throwable $e) {
      echo '-> anonymity check error: ' . $e->getMessage() . PHP_EOL;
    }

    // try geoip per protocol (best-effort)
    try {
      \PhpProxyHunter\GeoIpHelper::resolveGeoProxy($proxyStr, $type, $proxy_db);
    } catch (Throwable $e) {
      // ignore geoip errors
    }

    echo "-> working ($type) latency={$latency}ms endpoint=" . ($tested_endpoint ?? ($r['https'] ? 'https' : 'http')) . PHP_EOL;
  } else {
    echo "-> not working ($type)" . (isset($r['error']) ? ' error=' . $r['error'] : '') . PHP_EOL;
  }
}

// After testing all protocols, aggregate results and update DB once
$anyWorking = array_filter($results, fn ($v) => $v['result'] === true);
if (!empty($anyWorking)) {
  $types_str   = implode('-', $working_types);
  $latency_val = !empty($working_latencies) ? (string)min($working_latencies) : null;
  // private is true only if all working results were private
  $is_private = !empty($private_flags) && array_reduce($private_flags, fn ($carry, $item) => $carry && $item, true);
  $https_flag = false;
  foreach ($tested_endpoints_agg as $ep) {
    if (str_starts_with($ep, 'https')) {
      $https_flag = true;
      break;
    }
    if ($ep === 'https') {
      $https_flag = true;
      break;
    }
  }

  $update = [
    'type'    => $types_str,
    'status'  => 'active',
    'latency' => $latency_val,
    'private' => $is_private ? 'true' : 'false',
    'https'   => $https_flag ? 'true' : 'false',
  ];
  $proxy_db->updateData($proxyStr, $update);
  // pick first non-empty anonymity
  $anon = $anonymities[0] ?? null;
  if ($anon) {
    $proxy_db->updateData($proxyStr, ['anonymity' => $anon]);
  }
} else {
  // none worked
  if (!isPortOpen($proxyStr)) {
    $proxy_db->updateStatus($proxyStr, 'port-closed');
    echo 'Final: port closed' . PHP_EOL;
  } else {
    $proxy_db->updateStatus($proxyStr, 'dead');
    echo 'Final: dead' . PHP_EOL;
  }
}

// If none worked mark dead or port-closed
$anyWorking = array_filter($results, fn ($v) => $v['result'] === true);
if (empty($anyWorking)) {
  // If basic port closed detection
  // Try a raw port open check
  if (!isPortOpen($proxyStr)) {
    $proxy_db->updateStatus($proxyStr, 'port-closed');
    echo 'Final: port closed' . PHP_EOL;
  } else {
    $proxy_db->updateStatus($proxyStr, 'dead');
    echo 'Final: dead' . PHP_EOL;
  }
}

echo 'Done.' . PHP_EOL;

// Release lock file if created
if (file_exists($opts['lockFile'] ?? '')) {
  unlink($opts['lockFile']);
}
exit(0);
