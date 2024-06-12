<?php

// scan ports from generated ip ranges ports

require_once __DIR__ . '/../func-proxy.php';

use \PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Scheduler;

global $isCli;

// disallow web server access
if (!$isCli) {
  // Redirect the user away or show an error message
  header('HTTP/1.1 403 Forbidden');
  die('Direct access not allowed');
}

$max = 500; // default max proxies to be checked
$maxExecutionTime = 2 * 60; // 2 mins
$isAdmin = false;
$basename = basename(__FILE__, '.php');
$lockFilePath = __DIR__ . "/tmp/$basename.lock";
$statusFile = __DIR__ . "/status.txt";
$short_opts = "p:m::";
$long_opts = [
  "path:",
  "max::",
  "userId::",
  "lockFile::",
  "runner::",
  "admin::"
];
$options = getopt($short_opts, $long_opts);
if (!empty($options['lockFile'])) {
  $lockFilePath = $options['lockFile'];
}
if (!empty($options['max'])) {
  $max = intval($options['max']) > 0 ? intval($options['max']) : $max;
}
if (!empty($options['admin']) && $options['admin'] !== 'false') {
  $isAdmin = true;
  // set time limit 10 minutes for admin
  $maxExecutionTime = 10 * 60;
  // disable execution limit
  set_time_limit(0);
}

$folder = tmp() . '/ips-ports';
if (!empty($options['path']) && file_exists($options['path'])) {
  $filePath = $options['path'];
} else {
  $filePath = getRandomFileFromFolder($folder, 'txt');
}

if (file_exists($lockFilePath) && !is_debug() && !$isAdmin) {
  echo date(DATE_RFC3339) . ' another process still running' . PHP_EOL;
  exit();
} else {
  write_file($lockFilePath, date(DATE_RFC3339));
  write_file($statusFile, 'scan generated IP:PORT');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
  write_file($statusFile, 'idle');
}

register_shutdown_function('exitProcess');

// set memory
ini_set('memory_limit', '2024M');

// main script start

$startTime = time();
$str_to_remove = [];

if (file_exists($filePath)) {
  if (filesize($filePath) == 0) {
    unlink($filePath);
    exit("$filePath size 0");
  }

  iterateBigFilesLineByLine([$filePath], $max, function (string $line) use (&$str_to_remove, $startTime, $filePath, $maxExecutionTime) {
    $db = new ProxyDB();
    echo $line . PHP_EOL;
    $proxies = extractProxies($line, null, false);
    for ($i = 0; $i < count($proxies); $i++) {
      if (!is_debug()) {
        if (time() - $startTime > $maxExecutionTime) {
          echo "Execution time exceeded. Stopping execution." . PHP_EOL;
          break;
        }
      }
      $item = $proxies[$i];
      if (isPortOpen($item->proxy)) {
        // add to database on port open
        $db->updateData($item->proxy, ['status' => 'untested']);
        //      $http = checkProxy($item->proxy);
        //      $socks5 = checkProxy($item->proxy, 'socks5');
        //      $socks4 = checkProxy($item->proxy, 'socks4');
        //      if ($http || $socks4 || $socks5) {
        //        echo "$item->proxy working" . PHP_EOL;
        //      } else {
        //        echo "$item->proxy port open, but not proxy" . PHP_EOL;
        //      }
      } else {
        echo "$item->proxy port closed" . PHP_EOL;
      }
      $str_to_remove[] = $item->proxy;
      Scheduler::register(function () use ($filePath, $str_to_remove) {
        // echo 'removing ' . count($str_to_remove) . ' lines' . PHP_EOL;
        echo removeStringFromFile($filePath, $str_to_remove) . PHP_EOL;
      }, 'clean-up-' . basename(__FILE__));
    }
  });
}
