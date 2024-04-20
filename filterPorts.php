<?php

// filter open ports only

require __DIR__ . '/func.php';

use PhpProxyHunter\ProxyDB;

if (function_exists('header')) header('Content-Type: text/plain; charset=UTF-8');

$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath)) {
  echo "another process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'running');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath)) unlink($lockFilePath);
  file_put_contents($statusFile, 'idle');
}
register_shutdown_function('exitProcess');

$db = new ProxyDB();

$file = __DIR__ . '/proxies.txt';
// extract only IP:PORT
extractIpPortFromFile($file);
// remove empty lines
removeEmptyLinesFromFile($file);
$proxies = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
// shuffle proxies
shuffle($proxies);

// Record the start time
$start_time = microtime(true);

foreach (array_unique(array_filter($proxies, function ($value) {
  return !is_null($value) && $value !== '';
})) as $proxy) {
  // Check if execution time exceeds 120 seconds
  if (microtime(true) - $start_time > 120) {
    echo "Execution time exceeded 120 seconds. Exiting loop." . PHP_EOL;
    break;
  }

  if (!isPortOpen($proxy)) {
    removeStringAndMoveToFile($file, __DIR__ . '/dead.txt', trim($proxy));
    $db->updateStatus(trim($proxy), 'port-closed');
    echo trim($proxy) . " port closed" . PHP_EOL;
  }
}
