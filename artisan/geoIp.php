<?php

require_once __DIR__ . '/../php_backend/shared.php';

use PhpProxyHunter\GeoIpHelper;

global $isCli, $isWin;

ini_set('memory_limit', '512M');

/* CLI only */
if (!$isCli) {
  exit('web server access disallowed');
}

/* Options */
$options = getopt('', ['str:', 'file::', 'userId']);

if (!empty($options['userId'])) {
  setUserId($options['userId']);
}

$isAdmin      = is_admin();
$uid          = getUserId();
$config       = getConfig($uid);
$lockFilePath = tmp('locks', "geoIp-$uid.lock");
$statusFile   = get_project_root("tmp/logs/geoIp-$uid-status.txt");

/* DB */
$connections = refreshDbConnections(true);
$proxy_db    = $connections['proxy_db'];

/* Default */
$string_data = '89.58.45.94:45729';

/* CLI input */
if (isset($options['str'])) {
  $string_data = rawurldecode(trim($options['str']));
} elseif (!empty($options['file'])) {
  $filePath = trim($options['file']);
  if (file_exists($filePath) && is_file($filePath)) {
    $string_data = file_get_contents($filePath);
  } else {
    echo "Proxy file not found: $filePath\n";
    exit(1);
  }
} else {
  // fetch up to 100 proxies with missing geo fields using DB helper
  $where = "country IS NULL OR country = '' OR timezone IS NULL OR timezone = ''";

  // 1) Prefer active proxies first
  $activeWhere = "($where) AND status = 'active'";
  $activeRows  = $proxy_db->db->select('proxies', ['proxy', 'username', 'password'], $activeWhere, [], null, 100);

  // If we don't have enough active rows, fill the rest with non-active ones
  $rows = $activeRows;
  $need = 100 - count($rows);
  if ($need > 0) {
    $othersWhere = "($where) AND (status IS NULL OR status != 'active')";
    $more        = $proxy_db->db->select('proxies', ['proxy', 'username', 'password'], $othersWhere, [], null, $need);
    if (!empty($more)) {
      $rows = array_merge($rows, $more);
    }
  }

  if (empty($rows)) {
    echo "No proxies found with missing geo fields\n";
    exit(0);
  }

  // Build entries as IP:PORT@user:pass when credentials present
  $proxies = [];
  foreach ($rows as $r) {
    $p  = $r['proxy'];
    $u  = isset($r['username']) && $r['username'] !== '' ? $r['username'] : null;
    $pw = isset($r['password']) && $r['password'] !== '' ? $r['password'] : null;
    if ($u !== null && $pw !== null) {
      $proxies[] = $p . '@' . $u . ':' . $pw;
    } else {
      $proxies[] = $p;
    }
  }

  // join proxies into the input string (one per line) for extractor
  $string_data = implode(PHP_EOL, $proxies);
}

if (file_exists($lockFilePath) && !$isAdmin) {
  echo date(DATE_RFC3339) . ' another process still running' . PHP_EOL;
  exit();
} else {
  write_file($lockFilePath, date(DATE_RFC3339));
  write_file($statusFile, "geolocation $string_data");
}

\PhpProxyHunter\Scheduler::register(function () use ($lockFilePath, $statusFile) {
  echo 'releasing lock' . PHP_EOL;
  // clean lock files
  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
  echo 'update status to IDLE' . PHP_EOL;
  write_file($statusFile, 'idle');
}, 'z_onExit' . basename(__FILE__));

$extract = extractProxies($string_data, $proxy_db);
shuffle($extract);

$saveGeoForSameIp = static function (string $proxy, array $geoData) use ($proxy_db): int {
  // Keep only known geo columns and non-empty values.
  $allowedGeoKeys = ['lang', 'country', 'timezone', 'longitude', 'latitude', 'city', 'region'];
  $toSave         = [];
  foreach ($geoData as $key => $value) {
    if (in_array($key, $allowedGeoKeys, true) && (!empty($value) || $value === 0 || $value === '0')) {
      $toSave[$key] = $value;
    }
  }

  if (empty($toSave)) {
    return 0;
  }

  $proxy   = trim($proxy);
  $updated = 0;

  $proxy_db->updateData($proxy, $toSave);
  $updated++;

  $ips = extractIPs($proxy);
  if (empty($ips) || empty($ips[0])) {
    return $updated;
  }

  $ip   = $ips[0];
  $rows = $proxy_db->db->select('proxies', ['proxy'], 'proxy LIKE ?', ['%' . $ip . '%']);

  foreach ($rows as $row) {
    if (empty($row['proxy'])) {
      continue;
    }

    $candidateProxy = trim($row['proxy']);
    if ($candidateProxy === $proxy) {
      continue;
    }

    $candidateIps = extractIPs($candidateProxy);
    if (!empty($candidateIps) && isset($candidateIps[0]) && $candidateIps[0] === $ip) {
      $proxy_db->updateData($candidateProxy, $toSave);
      $updated++;
    }
  }

  return $updated;
};

foreach ($extract as $idx => $item) {
  echo 'Processing ' . ($idx + 1) . '/' . count($extract) . ' ' . $item->proxy . PHP_EOL;
  $fetched = false;
  if (empty($item->lang) || empty($item->country) || empty($item->timezone) || empty($item->longitude) || empty($item->latitude)) {
    $protocols = ['http', 'socks4', 'socks5', 'socks4a', 'socks5h'];
    $fetched   = false;
    // Ensure $result is always defined to satisfy static analysis
    // and avoid "undefined variable" notices when branches set $fetched.
    $result = [];
    foreach ($protocols as $protocol) {
      $result = GeoIpHelper::resolveGeoProxy($item->proxy, strtolower($protocol), $proxy_db);
      if (!empty($result) && !empty($result['country'])) {
        echo $item->proxy . ' geoip resolved using protocol ' . $protocol . PHP_EOL;
        $fetched = true;
        break;
      } else {
        echo $item->proxy . ' failed to resolve geoip using protocol ' . $protocol . PHP_EOL;
      }
    }
    // use simple method
    if (!$fetched) {
      $ip     = extractIPs($item->proxy)[0];
      $result = GeoIpHelper::getGeoIpSimple($ip, $proxy_db);
      if (!empty($result) && !empty($result['country'])) {
        $fetched = true;
        echo $item->proxy . ' geoip resolved using simple method' . PHP_EOL;
      } else {
        echo $item->proxy . ' failed to resolve geoip' . PHP_EOL;
      }
    }
    // use local mmdb as last resort
    if (!$fetched) {
      $ip       = extractIPs($item->proxy)[0];
      $mmdbPath = get_project_root('src', 'GeoLite2-City.mmdb');
      if (file_exists($mmdbPath)) {
        // try to use local mmdb
        $reader = new \GeoIp2\Database\Reader($mmdbPath);
        try {
          $record = $reader->city($ip);

          // Build result using isset checks to avoid property access on null (PHP 7.0 safe)
          $country = null;
          if (isset($record->country) && isset($record->country->name) && $record->country->name !== '') {
            $country = $record->country->name;
          }

          $timezone = null;
          if (isset($record->location) && isset($record->location->timeZone) && $record->location->timeZone !== '') {
            $timezone = $record->location->timeZone;
          }

          $longitude = null;
          if (isset($record->location) && isset($record->location->longitude) && $record->location->longitude !== '') {
            $longitude = $record->location->longitude;
          }

          $latitude = null;
          if (isset($record->location) && isset($record->location->latitude) && $record->location->latitude !== '') {
            $latitude = $record->location->latitude;
          }

          $city = null;
          if (isset($record->city) && isset($record->city->name) && $record->city->name !== '') {
            $city = $record->city->name;
          }

          $lang = null;
          if (isset($record->country) && isset($record->country->names) && is_array($record->country->names) && isset($record->country->names['en']) && $record->country->names['en'] !== '') {
            $lang = $record->country->names['en'];
          } elseif ($country !== null) {
            $lang = $country;
          }

          $result = [
            'country'   => $country,
            'timezone'  => $timezone,
            'longitude' => $longitude,
            'latitude'  => $latitude,
            'city'      => $city,
            'lang'      => $lang,
          ];

          if (!empty($result['country'])) {
            $fetched = true;
            echo $item->proxy . ' geoip resolved using local mmdb' . PHP_EOL;
          } else {
            echo $item->proxy . ' failed to resolve geoip using local mmdb' . PHP_EOL;
            // debug
            // if (!empty($record)) {
            //   exit(var_dump($record));
            // }
          }
        } catch (Exception $e) {
          echo $item->proxy . ' exception using local mmdb: ' . $e->getMessage() . PHP_EOL;
        }

        $reader->close();
      } else {
        echo $item->proxy . ' mmdb file not found' . PHP_EOL;
      }
    }
    if ($fetched) {
      $savedCount = $saveGeoForSameIp($item->proxy, $result);
      if ($savedCount > 0) {
        echo $item->proxy . ' geoip propagated to ' . $savedCount . ' row(s) with same IP' . PHP_EOL;
      }
      foreach ($result as $key => $value) {
        $item->$key = $value;
      }
    }
  } else {
    echo $item->proxy . ' has geoip data, skip' . PHP_EOL;
  }
  if (empty($item->useragent)) {
    $item->useragent = randomWindowsUa();
    $proxy_db->updateData($item->proxy, ['useragent' => $item->useragent]);
    echo $item->proxy . ' missing useragent fix' . PHP_EOL;
  } else {
    echo $item->proxy . ' has useragent, skip' . PHP_EOL;
  }
  $webgl_missing = empty($item->webgl_renderer) || empty($item->browser_vendor) || empty($item->webgl_vendor);
  if ($webgl_missing) {
    $webgl = random_webgl_data();
    $proxy_db->updateData($item->proxy, [
      'webgl_renderer' => $webgl->webgl_renderer,
      'webgl_vendor'   => $webgl->webgl_vendor,
      'browser_vendor' => $webgl->browser_vendor,
    ]);
    echo $item->proxy . ' missing WebGL fix' . PHP_EOL;
  } else {
    echo $item->proxy . ' has WebGL data, skip' . PHP_EOL;
  }

  if ($webgl_missing || $fetched) {
    // Output result
    $itemArray = $proxy_db->select($item->proxy)[0];
    foreach ($itemArray as $key => $value) {
      echo "  $key: $value" . PHP_EOL;
    }
  }
}

writing_working_proxies_file($proxy_db);
