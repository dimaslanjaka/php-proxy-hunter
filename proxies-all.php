<?php

// index all proxies into database

require_once __DIR__ . "/func-proxy.php";

use PhpProxyHunter\ProxyDB;

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
  foreach ($items as $proxy) {
    if (empty($proxy->proxy)) continue;
    $sel = $db->select($proxy->proxy);
    if (empty($sel)) {
      echo "add $proxy->proxy" . PHP_EOL;
      // add proxy
      $db->add($proxy->proxy);
      // re-select proxy
      $sel = $db->select($proxy->proxy);
    }
    if (is_null($sel[0]['status'])) {
      $db->updateStatus($proxy->proxy, 'untested');
    }
  }
});

// Release the lock
flock($lockHandle, LOCK_UN);
fclose($lockHandle);
unlink($lockFile);
