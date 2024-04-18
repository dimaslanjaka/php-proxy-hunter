<?php

// filter open ports only

require __DIR__ . '/func.php';

use PhpProxyHunter\ProxyDB;

$db = new ProxyDB();

$file = __DIR__ . '/proxies.txt';
removeEmptyLinesFromFile($file);
$proxies = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
shuffle($proxies);
// $openPortsOnly = array_filter($proxies, 'isPortOpen');
// file_put_contents(__DIR__ . '/tmp/test.txt', join(PHP_EOL, $openPortsOnly));
foreach (array_unique(array_filter($proxies, function ($value) {
  return !is_null($value) && $value !== '';
})) as $proxy) {
  if (!isPortOpen($proxy)) {
    removeStringAndMoveToFile($file, __DIR__ . '/dead.txt', trim($proxy));
    $db->updateStatus(trim($proxy), 'port-closed');
    echo trim($proxy) . " port closed" . PHP_EOL;
  }
}
