<?php

require_once __DIR__ . '/func-proxy.php';
require_once __DIR__ . '/php_backend/shared.php';

use PhpProxyHunter\GeoIpHelper;
use PhpProxyHunter\Server;

global $isCli, $isWin, $proxy_db;

if (!$isCli) {
  exit('web server access disallowed');
}

ini_set('memory_limit', '512M');

if (function_exists('header') && !$isCli) {
  Server::allowCors(true);

  // Set content type to plain text with UTF-8 encoding
  header('Content-Type: text/plain; charset=utf-8');

  // check admin
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

$db           = $proxy_db;
$lockFilePath = tmp() . '/locks/geoIp.lock';
$statusFile   = __DIR__ . '/status.txt';
$config       = getConfig(getUserId());
$options      = getopt('', ['str:']); // php geoIp.php --str "xsdsd dfdfd"

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

\PhpProxyHunter\Scheduler::register(function () use ($lockFilePath, $statusFile, $db) {
  echo 'releasing lock' . PHP_EOL;
  // clean lock files
  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
  echo 'update status to IDLE' . PHP_EOL;
  write_file($statusFile, 'idle');
}, 'z_onExit' . basename(__FILE__));

if (function_exists('header')) {
  // Set cache control headers to instruct the browser to cache the content for [n] hour
  $hour = 1;
  header('Cache-Control: max-age=3600, must-revalidate');
  header('Expires: ' . gmdate('D, d M Y H:i:s', time() + ($hour * 3600)) . ' GMT');
}

$extract = extractProxies($string_data, $db);
shuffle($extract);

foreach ($extract as $item) {
  if (empty($item->lang) || empty($item->country) || empty($item->timezone) || empty($item->longitude) || empty($item->latitude)) {
    GeoIpHelper::getGeoIp($item->proxy, 'http', $db);
  }
  if (empty($item->useragent)) {
    $item->useragent = randomWindowsUa();
    $db->updateData($item->proxy, ['useragent' => $item->useragent]);
    echo $item->proxy . ' missing useragent fix' . PHP_EOL;
  }
  if (empty($item->webgl_renderer) || empty($item->browser_vendor) || empty($item->webgl_vendor)) {
    $webgl = random_webgl_data();
    $db->updateData($item->proxy, [
      'webgl_renderer' => $webgl->webgl_renderer,
      'webgl_vendor'   => $webgl->webgl_vendor,
      'browser_vendor' => $webgl->browser_vendor,
    ]);
    echo $item->proxy . ' missing WebGL fix' . PHP_EOL;
  }
}
