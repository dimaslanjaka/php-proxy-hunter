<?php

require_once __DIR__ . "/func-proxy.php";

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

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
  file_put_contents($statusFile, 'cleaning proxies');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath))
    unlink($lockFilePath);
  file_put_contents($statusFile, 'idle');
}

register_shutdown_function('exitProcess');

// clean all proxies

$all = __DIR__ . '/proxies-all.txt';

// Define file paths array
$files = [
    __DIR__ . '/proxies.txt',
    __DIR__ . '/working.txt',
    __DIR__ . '/dead.txt'
];

setFilePermissions(array_merge($files, [$all]));

echo "removing duplicated lines from proxies.txt which exist in dead.txt" . PHP_EOL;

try {
  // remove duplicate lines in untested proxies
  $file1 = __DIR__ . "/proxies.txt";
  $file2 = __DIR__ . "/dead.txt";

  if (removeDuplicateLinesFromSource($file1, $file2)) {
    echo "Duplicated lines between $file1 and $file2 removed successful from $file1" . PHP_EOL;
  }
} catch (Exception $e) {
  // Handle any exceptions that occur during the execution of removeDuplicateLinesInUntestedProxies
  echo 'Error removing duplicate lines in untested proxies: ' . $e->getMessage() . PHP_EOL;
}

foreach ($files as $file) {
  echo "processing $file" . PHP_EOL;

  try {
    // remove duplicate lines from proxies.txt
    removeDuplicateLines($file);
  } catch (Exception $e) {
    // Handle any exceptions that occur during the execution of removeDuplicateLines
    echo 'Error removing duplicate lines ' . $e->getMessage() . PHP_EOL;
  }

  try {
    // remove lines less than 10 size
    removeShortLines($file, 10);
  } catch (Exception $e) {
    // Handle any exceptions that occur during the execution of removeDuplicateLines
    echo 'Error removing short lines ' . $e->getMessage() . PHP_EOL;
  }

  echo "remove lines not contains IP:PORT" . PHP_EOL;

  try {
    filterIpPortLines($file);
  } catch (InvalidArgumentException $e) {
    echo "Lines not containing IP:PORT format remove failed. " . $e->getMessage() . PHP_EOL;
  }

  echo "remove empty lines" . PHP_EOL;

  try {
    removeEmptyLinesFromFile($file);
  } catch (\Throwable $th) {
    echo 'Error fix bad contents from proxies.txt: ' . $th->getMessage() . PHP_EOL;
  }

  echo "fix file NUL" . PHP_EOL;

  try {
    fixFile($file);
  } catch (\Throwable $th) {
    echo 'Error fix bad contents from proxies.txt: ' . $th->getMessage() . PHP_EOL;
  }
}

//echo "removing dead proxies from untested file" . PHP_EOL;
//
//iterateBigFilesLineByLine([__DIR__ . '/proxies.txt'], function (string $line) {
//  $db = new ProxyDB();
//  $proxies = extractProxies(trim($line));
//  foreach ($proxies as $item) {
//    if (empty(trim($item->proxy))) continue;
//    $sel = $db->select($item->proxy);
//    if (!empty($sel)) {
//      $status = $sel[0]['status'];
//      if ($status == 'dead') {
//        removeStringFromFile(__DIR__ . '/proxies.txt', trim($item->proxy));
//        echo $item->proxy . " removed" . PHP_EOL;
//      }
//    }
//  }
//});

//  if (confirmAction("Are you want move $file content into $all:\t")) {
//    $content = read_file($file);
//    append_content_with_lock($all, $content);
//  }