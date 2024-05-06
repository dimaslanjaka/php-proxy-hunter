<?php

// index all proxies into database

require_once __DIR__ . "/func-proxy.php";

use PhpProxyHunter\ProxyDB;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');
if (!$isCli)
  exit('web server access disallowed');

$db = new ProxyDB();

/**
 * Iterate over multiple big files line by line and execute a callback for each line.
 *
 * @param array $filePaths Array of file paths to iterate over.
 * @param callable $callback Callback function to execute for each line.
 */
function iterateFilesLineByLine(array $filePaths, callable $callback)
{
  foreach ($filePaths as $filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
      // Handle file not found or not readable
      continue;
    }
    fixFile($filePath);

    $file = fopen($filePath, 'r');
    if ($file) {
      while (($line = fgets($file)) !== false) {
        // Execute callback for each line
        call_user_func($callback, $line);
      }
      fclose($file);
    } else {
      echo "failed open $filePath" . PHP_EOL;
    }
  }
}

function processLine($line)
{
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
}

$files = [__DIR__ . '/dead.txt', __DIR__ . '/proxies.txt', __DIR__ . '/proxies-all.txt'];
iterateFilesLineByLine($files, 'processLine');
