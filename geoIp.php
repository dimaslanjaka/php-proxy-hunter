<?php

require_once __DIR__ . '/func-proxy.php';

$isCli = strtolower(php_sapi_name()) === 'cli';

if (function_exists('header')) {
  header('Content-Type: application/json; charset=UTF-8');

  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: application/json; charset=utf-8');
}

$db = new \PhpProxyHunter\ProxyDB(__DIR__ . '/src/database.sqlite');
$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";
$config = getConfig(getUserId());
$options = getopt("", ["str:"]); // php geoIp.php --str "xsdsd dfdfd"

$string_data = '89.58.45.94:45729';
if ($isCli) {
  if (isset($options['str'])) {
    $string_data = rawurldecode(trim($options['str']));
  } else {
    $read_data = read_file(__DIR__ . '/proxies.txt');
    if (!empty($read_data)) $string_data = $read_data;
  }
} else if (isset($_REQUEST['proxy'])) {
  $string_data = rawurldecode(trim($_REQUEST['proxy']));
}

if (file_exists($lockFilePath) && !is_debug()) {
  echo "proxy checker process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, "geolocation $string_data");
}

\PhpProxyHunter\Scheduler::register(function () use ($lockFilePath, $statusFile, $db) {
  echo "releasing lock" . PHP_EOL;
  // clean lock files
  if (file_exists($lockFilePath))
    unlink($lockFilePath);
  echo "update status to IDLE" . PHP_EOL;
  file_put_contents($statusFile, 'idle');
}, 'z_onExit' . __FILE__);

if (function_exists('header')) {
  // Set cache control headers to instruct the browser to cache the content for [n] hour
  $hour = 1;
  header('Cache-Control: max-age=3600, must-revalidate');
  header('Expires: ' . gmdate('D, d M Y H:i:s', time() + ($hour * 3600)) . ' GMT');
}

$extract = extractProxies($string_data);
shuffle($extract);

foreach ($extract as $item) {
  get_geo_ip($item->proxy, 'http', $db);
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
