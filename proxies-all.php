<?php

// index all proxies into database

require_once __DIR__ . "/func-proxy.php";

use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Scheduler;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');
if (!$isCli)
  exit('web server access disallowed');

$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath) && gethostname() !== 'DESKTOP-JVTSJ6I') {
  echo "another process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'indexing proxies');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath))
    unlink($lockFilePath);
  file_put_contents($statusFile, 'idle');
}

register_shutdown_function('exitProcess');

$db = new ProxyDB();

//$files = [__DIR__ . '/proxies.txt'];
$files = [__DIR__ . '/dead.txt', __DIR__ . '/proxies.txt', __DIR__ . '/proxies-all.txt'];
$assets = array_filter(getFilesByExtension(__DIR__ . '/assets/proxies'), function ($fn) {
  return strpos($fn, 'added-') !== false;
});
if (!empty($assets)) {
  $files = array_filter(array_merge($files, $assets), 'file_exists');
  $files = array_map('realpath', $files);
}

$str_to_remove = [];

foreach ($files as $file) {
  echo filterIpPortLines($file) . PHP_EOL;
  $read = read_file($file);
  $isFileEmpty = (is_string($read) && empty(trim($read))) || filesize($file) == 0;
  // Check if file is empty
  if ($isFileEmpty) {
    // Delete the file
    unlink($file);
    echo "Deleted empty file: " . basename($file) . PHP_EOL;
  }
}

iterateBigFilesLineByLine($files, function ($line) use ($db, &$str_to_remove) {
  $items = extractProxies($line, $db, false);
  foreach ($items as $item) {
    if (empty($item->proxy)) continue;
    if (!isValidProxy($item->proxy)) {
      if (count($str_to_remove) < 5000) $str_to_remove[] = $item->proxy;
      echo $item->proxy . ' invalid' . PHP_EOL;
      continue;
    }
    $sel = $db->select($item->proxy);
    if (empty($sel)) {
      echo "add " . $item->proxy . PHP_EOL;
      // add proxy
      $db->add($item->proxy);
      // re-select proxy
      $sel = $db->select($item->proxy);
    }
    if (empty($sel[0]['status'])) {
      echo "treat as untested " . $item->proxy . PHP_EOL;
      $db->updateStatus($item->proxy, 'untested');
    }
    if (!empty($sel[0]['proxy']) && !isValidProxy($sel[0]['proxy'])) {
      if (count($str_to_remove) < 5000) $str_to_remove[] = $sel[0]['proxy'];
    }
  }
});

echo "iterating all proxies" . PHP_EOL;
$db->iterateAllProxies(function ($item) use ($db, &$str_to_remove) {
  if (!empty($item['proxy'])) {
    if (!isValidProxy($item['proxy'])) {
      // remove invalid proxy from database
      echo '[SQLite] remove invalid proxy (' . $item['proxy'] . ')' . PHP_EOL;
      $db->remove($item['proxy']);
    } else {
      $sel = $db->select($item['proxy']);
      // push indexed proxies to be removed from files
      if (count($str_to_remove) < 5000) $str_to_remove[] = $sel[0]['proxy'];
    }
  }
});

if (!empty($str_to_remove)) {
  foreach ($files as $file) {
    Scheduler::register(function () use (&$str_to_remove, $file) {
      if (removeStringFromFile($file, $str_to_remove) == 'success') {
        echo "[FILE] removed indexed proxies from " . basename($file) . ' ' . count($str_to_remove) . PHP_EOL;
      }
    }, "[FILE] remove indexed " . $file);
  }
} else {
  echo "No proxies to remove" . PHP_EOL;
}
