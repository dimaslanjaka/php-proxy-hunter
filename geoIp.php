<?php

require_once __DIR__ . '/func-proxy.php';

if (function_exists('set_time_limit')) {
  call_user_func('set_time_limit', 120);
}

if (function_exists('header')) header('Content-Type: application/json; charset=UTF-8');

$lockFilePath = __DIR__ . "/proxyChecker.lock";

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

$proxy = '112.30.155.83:12792';
if (strtolower(php_sapi_name()) === 'cli') {
  $proxy = file_get_contents(__DIR__ . '/proxies.txt');
} else if (isset($_REQUEST['proxy'])) {
  $proxy = $_REQUEST['proxy'];
}

$extract = extractProxies($proxy);
shuffle($extract);
foreach ($extract as $item) {
  get_geo_ip($item->proxy);
}
