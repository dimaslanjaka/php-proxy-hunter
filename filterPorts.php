<?php

// filter open ports only

require __DIR__ . '/func-proxy.php';

use PhpProxyHunter\ProxyDB;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');
if (!$isCli)
  exit('web server access disallowed');

$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath)) {
  echo "another process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'filter-ports');
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
// remove empty lines
removeEmptyLinesFromFile($file);

// Record the start time
$start_time = microtime(true);

extractIpPortFromFileCallback($file, function ($proxy) use ($start_time, $isCli, $file, $db) {
  // Check if execution time exceeds [n] seconds
  if (microtime(true) - $start_time > (!$isCli ? 120 : 300)) {
    // echo "Execution time exceeded 120 seconds. Exiting loop." . PHP_EOL;
    return;
  }
  if (!isPortOpen($proxy)) {
    removeStringAndMoveToFile($file, __DIR__ . '/dead.txt', $proxy);
    $db->updateStatus($proxy, 'port-closed');
    echo $proxy . " port closed" . PHP_EOL;
  }
});

// remove non IP:PORT from database

$all = $db->getAllProxies();
foreach ($all as $data) {
  $invalidIPPORT = !preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+$/', $data['proxy']);
  $invalidIP = strpos($data['proxy'], '0.0.0.0') !== false;
  if ($invalidIPPORT || $invalidIP) {
    echo $data['proxy'] . " is not in the format of IP:PORT." . PHP_EOL;
    $db->remove($data['proxy']);
  }
}
