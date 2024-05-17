<?php

// scan ports from generated ip ranges ports

require_once __DIR__ . '/../func-proxy.php';

use \PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Scheduler;

// disallow web server access
if (php_sapi_name() !== 'cli') {
  // Redirect the user away or show an error message
  header('HTTP/1.1 403 Forbidden');
  die('Direct access not allowed');
}

$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath) && !is_debug()) {
  echo "another process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'scan generated IP:PORT');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath)) unlink($lockFilePath);
  file_put_contents($statusFile, 'idle');
}

register_shutdown_function('exitProcess');

// set memory
ini_set('memory_limit', '2024M');

$filePath = getRandomFileFromFolder(tmp() . '/ips-ports', 'txt');
$outputPath = __DIR__ . '/../proxies.txt';
$startTime = time();
$str_to_remove = [];
if (filesize($filePath) == 0) {
  unlink($filePath);
  exit("$filePath size 0");
}

iterateBigFilesLineByLine([$filePath], 55, function (string $line) use (&$str_to_remove, $startTime, $filePath) {
  $db = new ProxyDB();
  $proxies = extractProxies($line, null, false);
  for ($i = 0; $i < count($proxies); $i++) {
    if (!is_debug()) {
      if (time() - $startTime > 300) {
        echo "Execution time exceeded. Stopping execution." . PHP_EOL;
        break;
      }
    }
    $item = $proxies[$i];
    if (isPortOpen($item->proxy)) {
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
      echo 'removing ' . count($str_to_remove) . ' lines' . PHP_EOL;
      removeStringFromFile($filePath, $str_to_remove);
    }, 'clean-up-' . basename(__FILE__));
  }
});

