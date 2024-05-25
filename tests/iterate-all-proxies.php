<?php

require __DIR__ . '/../func-proxy.php';

use PhpProxyHunter\ProxyDB;

$db = new ProxyDB(__DIR__ . '/../src/database.sqlite');
$db->iterateAllProxies(function ($item) {
  //  echo $item['proxy'] . ' is valid ' . (isValidProxy($item['proxy']) ? 'true' : 'false') . PHP_EOL;
  if (!isValidProxy($item['proxy'])) {
    echo $item['proxy'] . PHP_EOL;
  }
});
