<?php

require_once __DIR__ . '/func-proxy.php';

if (file_exists(__DIR__ . '/proxyChecker.lock') && gethostname() !== 'DESKTOP-JVTSJ6I') {
  exit('proxy checker process still running');
}

if (function_exists('set_time_limit')) {
  call_user_func('set_time_limit', 120);
}

if (function_exists('header')) header('Content-Type: application/json; charset=UTF-8');

$string_data = '112.30.155.83:12792';
if (strtolower(php_sapi_name()) === 'cli') {
  $string_data = file_get_contents(__DIR__ . '/proxies.txt');
} else if (isset($_REQUEST['proxy'])) {
  $string_data = $_REQUEST['proxy'];
}

$lockFolder = realpath(__DIR__ . '/tmp');
$lockFilePath = $lockFolder . "/" . md5($string_data) . ".lock";

if (file_exists($lockFilePath) && gethostname() !== "DESKTOP-JVTSJ6I") {
  exit(json_encode(['error' => 'another process still running']));
} else {
  file_put_contents($lockFilePath, '');
}
function exitProcess()
{
  global $lockFilePath;
  if (file_exists($lockFilePath)) unlink($lockFilePath);
}

register_shutdown_function('exitProcess');

$extract = extractProxies($string_data);
shuffle($extract);

$db = new \PhpProxyHunter\ProxyDB(__DIR__ . '/src/database.sqlite');

foreach ($extract as $item) {
  get_geo_ip($item->proxy);
  if (empty($item->useragent)) {
    $item->useragent = randomWindowsUa();
    $db->updateData($item->proxy, ['useragent' => $item->useragent]);
    echo $item->proxy . " missing useragent fix" . PHP_EOL;
  }
  if (empty($item->webgl_renderer) || empty($item->browser_vendor) || empty($item->webgl_vendor)) {
    $webgl = random_webgl_data();
    $db->updateData($item->proxy, [
        'webgl_renderer' => $webgl->webgl_renderer,
        'webgl_vendor' => $webgl->webgl_vendor,
        'browser_vendor' => $webgl->browser_vendor
    ]);
    echo $item->proxy . " missing WebGL fix" . PHP_EOL;
  }
}
