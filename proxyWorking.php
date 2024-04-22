<?php

// proxies writer

require __DIR__ . '/func.php';

use PhpProxyHunter\ProxyDB;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

header('Content-Type:text/plain; charset=UTF-8');

if (file_exists(__DIR__ . '/proxyChecker.lock')) {
  exit('another process still running');
}

$db = new ProxyDB();
$working = $db->getWorkingProxies();

$impl = implode(PHP_EOL, array_map(function ($item) {
  return implode("|", [$item['proxy'], $item['latency'] ? $item['latency'] : '-', strtoupper($item['type']), $item['region'] ? $item['region'] : '-', $item['city'] ? $item['city'] : '-', $item['country'] ? $item['country'] : '-', $item['timezone'] ? $item['timezone'] : '-', $item['last_check'] ? $item['last_check'] : '-']);
}, $working));

$header = 'PROXY|LATENCY|TYPE|REGION|CITY|COUNTRY|TIMEZONE|LAST CHECK DATE';
// echo implode(PHP_EOL, [$header, $impl]);

// Explode the input into an array of lines
$lines = explode("\n", $impl);

// Sort the lines alphabetically
sort($lines);

$impl = join("\n", $lines);

file_put_contents(__DIR__ . '/working.txt', $impl);

if (!$isCli) {
  if (!isset($_COOKIE['rewrite_cookies'])) {
    // Set the cookie to expire in 5 minutes (300 seconds)
    $cookie_value = md5(date(DATE_RFC3339));
    setcookie('rewrite_cookies', $cookie_value, time() + 300, '/');

    // rewrite working proxies to be checked again later
    file_put_contents(__DIR__ . '/proxies.txt', PHP_EOL . $impl . PHP_EOL, FILE_APPEND);
  }
}

$untested = countNonEmptyLines(__DIR__ . '/proxies.txt');
$dead = countNonEmptyLines(__DIR__ . '/dead.txt');
echo "total working proxies " . count($working) . PHP_EOL;
echo "total dead proxies $dead" . PHP_EOL;
echo "total untested proxies $untested" . PHP_EOL;

file_put_contents(__DIR__ . '/status.json', json_encode(['working' => count($working), 'dead' => $dead, 'untested' => $untested]));
