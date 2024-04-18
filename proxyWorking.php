<?php

require __DIR__ . '/func.php';

use PhpProxyHunter\ProxyDB;

header('Content-Type:text/plain; charset=UTF-8');

$workingHttp = __DIR__ . '/working.txt';
$workingSocks = __DIR__ . '/socks-working.txt';

$db = new ProxyDB();
$working = $db->getWorkingProxies();

$impl = implode(PHP_EOL, array_map(function ($item) {
  return implode("|", [$item['proxy'], $item['latency'] ? $item['latency'] : '-', strtoupper($item['type']), $item['region'] ? $item['region'] : '-', $item['city'] ? $item['city'] : '-', $item['country'] ? $item['country'] : '-', $item['timezone'] ? $item['timezone'] : '-', $item['last_check'] ? $item['last_check'] : '-']);
}, $working));

$header = 'PROXY|LATENCY|TYPE|REGION|CITY|COUNTRY|TIMEZONE|LAST CHECK DATE';
// echo implode(PHP_EOL, [$header, $impl]);
file_put_contents($workingHttp, $impl);
echo "total working proxies " + count($working);
