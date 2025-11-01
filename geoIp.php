<?php

require_once __DIR__ . '/func-proxy.php';
require_once __DIR__ . '/php_backend/shared.php';

use PhpProxyHunter\GeoIpHelper;

global $isCli, $isWin, $proxy_db;

ini_set('memory_limit', '512M');

if (!$isCli) {
  exit('web server access disallowed');
}

$options = getopt('', ['str:', 'userId']); // php geoIp.php --str "xsdsd dfdfd"
if (!empty($options['userId'])) {
  setUserId($options['userId']);
}
$uid          = getUserId();
$lockFilePath = tmp() . '/locks/geoIp-' . $uid . '.lock';
$statusFile   = __DIR__ . '/status.txt';
$config       = getConfig($uid);

$string_data = '89.58.45.94:45729';
if ($isCli) {
  if (isset($options['str'])) {
    $string_data = rawurldecode(trim($options['str']));
  } else {
    $read_data = read_file(__DIR__ . '/proxies.txt');
    if (!empty($read_data)) {
      $string_data = $read_data;
    }
  }
} elseif (isset($_REQUEST['proxy'])) {
  $string_data = rawurldecode(trim($_REQUEST['proxy']));
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

foreach ($extract as $item) {
  echo 'Processing ' . $item->proxy . PHP_EOL;
  if (empty($item->lang) || empty($item->country) || empty($item->timezone) || empty($item->longitude) || empty($item->latitude)) {
    GeoIpHelper::getGeoIp($item->proxy, 'http', $proxy_db);
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
  if (empty($item->webgl_renderer) || empty($item->browser_vendor) || empty($item->webgl_vendor)) {
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
}

writing_working_proxies_file($proxy_db);
