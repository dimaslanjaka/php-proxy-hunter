<?php

// index all proxies into database

require_once __DIR__ . "/func-proxy.php";

use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Scheduler;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');
if (!$isCli)
  exit('web server access disallowed');

$lockFile = __DIR__ . "/tmp/" . md5(__FILE__) . ".lock";

// Attempt to acquire a lock
$lockHandle = fopen($lockFile, 'w');

if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
  // Failed to acquire lock, another instance is running
  echo "Another instance is already running.\n";
  exit(1);
}

$db = new ProxyDB();
$files = [__DIR__ . '/dead.txt', __DIR__ . '/proxies.txt', __DIR__ . '/proxies-all.txt'];

iterateBigFilesLineByLine($files, function ($line) {
  global $db;
  $items = extractProxies($line);
  foreach ($items as $item) {
    if (empty($item->proxy)) continue;
    $sel = $db->select($item->proxy);
    if (empty($sel)) {
      echo "add $item->proxy" . PHP_EOL;
      // add proxy
      $db->add($item->proxy);
      // re-select proxy
      $sel = $db->select($item->proxy);
    }
    if (is_null($sel[0]['status'])) {
      $db->updateStatus($item->proxy, 'untested');
    }
    if (!empty($sel[0]['proxy'])) {
      Scheduler::register(function () use ($db, $item) {
        echo "removing " . $item->proxy . PHP_EOL;
        $db->remove($item->proxy);
      }, "remove " . $item->proxy);
    }
  }
});

// Release the lock
flock($lockHandle, LOCK_UN);
fclose($lockHandle);
unlink($lockFile);
