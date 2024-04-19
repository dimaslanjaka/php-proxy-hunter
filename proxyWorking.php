<?php

// proxies writer

require __DIR__ . '/func.php';

use PhpProxyHunter\ProxyDB;

header('Content-Type:text/plain; charset=UTF-8');

$workingHttp = __DIR__ . '/working.txt';
// $workingSocks = __DIR__ . '/socks-working.txt';

if (file_exists(__DIR__ . '/proxyChecker.lock')) {
  exit('another process still running');
}

$db = new ProxyDB();
$working = $db->getWorkingProxies();
$dead = $db->getDeadProxies();
$untested = file(__DIR__ . '/proxies.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!is_array($untested)) $untested = [];
$untested = array_unique($untested);

$impl = implode(PHP_EOL, array_map(function ($item) {
  return implode("|", [$item['proxy'], $item['latency'] ? $item['latency'] : '-', strtoupper($item['type']), $item['region'] ? $item['region'] : '-', $item['city'] ? $item['city'] : '-', $item['country'] ? $item['country'] : '-', $item['timezone'] ? $item['timezone'] : '-', $item['last_check'] ? $item['last_check'] : '-']);
}, $working));

$header = 'PROXY|LATENCY|TYPE|REGION|CITY|COUNTRY|TIMEZONE|LAST CHECK DATE';
// echo implode(PHP_EOL, [$header, $impl]);
file_put_contents($workingHttp, $impl);

echo "total working proxies " . count($working) . PHP_EOL;
echo "total dead proxies " . count($working) . PHP_EOL;
echo "total untested proxies " . count($untested) . PHP_EOL;

file_put_contents(__DIR__ . '/status.json', json_encode(['working' => count($working), 'dead' => count($dead), 'untested' => count($untested)]));
