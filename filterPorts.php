<?php

// filter open ports only

require __DIR__ . '/func-proxy.php';

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  header('Content-Type:text/plain; charset=UTF-8');
}
if (!$isCli) {
  exit('web server access disallowed');
}

$isAdmin = false; // admin indicator
$max_checks = 500; // max proxies to be checked
$maxExecutionTime = 120; // max 120s execution time

if ($isCli) {
  $short_opts = "p:m::";
  $long_opts = [
    "proxy:",
    "max::",
    "userId::",
    "lockFile::",
    "runner::",
    "admin::"
  ];
  $options = getopt($short_opts, $long_opts);
  if (!empty($options['max'])) {
    $max = intval($options['max']);
    if ($max > 0) {
      $max_checks = $max;
    }
  }
  if (!empty($options['admin']) && $options['admin'] !== 'false') {
    $isAdmin = true;
    // set time limit 10 minutes for admin
    $maxExecutionTime = 10 * 60;
    // disable execution limit
    set_time_limit(0);
  }
}

$lockFilePath = tmp() . "/runners/" . basename(__FILE__, '.php') . ".lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath) && !is_debug()) {
  exit(date(DATE_RFC3339) . ' another process still running ' . basename(__FILE__, '.php') . PHP_EOL);
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'filter-ports');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
  file_put_contents($statusFile, 'idle');
}

register_shutdown_function('exitProcess');

$db = new ProxyDB();

$file = __DIR__ . '/proxies.txt';

// remove empty lines
removeEmptyLinesFromFile($file);

// Record the start time
$start_time = microtime(true);

try {
  $db_data = $db->getUntestedProxies(100);
  $db_data_map = array_map(function ($item) {
    // transform array into Proxy instance same as extractProxies result
    $wrap = new Proxy($item['proxy']);
    foreach ($item as $key => $value) {
      if (property_exists($wrap, $key)) {
        $wrap->$key = $value;
      }
    }
    if (!empty($item['username']) && !empty($item['password'])) {
      $wrap->username = $item['username'];
      $wrap->password = $item['password'];
    }
    return $wrap;
  }, $db_data);
  if (empty($db_data_map)) {
    $read = read_first_lines($file, $max_checks);
    if (!$read) {
      $read = [];
    }
    $proxies = extractProxies(implode("\n", $read), $db, false);
    $proxies = array_merge($proxies, $db_data_map);
  } else {
    // prioritize untested proxies from database
    $proxies = $db_data_map;
  }

  shuffle($proxies);

  // Convert the array of Proxy objects into an iterator
  $proxyIterator = new ArrayIterator($proxies);

  // Create a MultipleIterator
  $multipleIterator = new MultipleIterator(MultipleIterator::MIT_NEED_ALL);

  // Attach the Proxy iterator to the MultipleIterator
  $multipleIterator->attachIterator($proxyIterator);

  // Iterate over the MultipleIterator
  foreach ($multipleIterator as $proxyInfo) {
    // $proxyInfo is an array containing each Proxy object
    foreach ($proxyInfo as $proxy) {
      processProxy($proxy->proxy);
    }
  }
} catch (Exception $e) {
  echo "fail extracting proxies " . $e->getMessage() . PHP_EOL;
}

function processProxy($proxy)
{
  global $start_time, $file, $db, $maxExecutionTime;
  // Check if execution time exceeds [n] seconds
  if (microtime(true) - $start_time > $maxExecutionTime) {
    // echo "Execution time exceeded $maxExecutionTime seconds. Exiting loop." . PHP_EOL;
    return;
  }
  if (!isPortOpen($proxy)) {
    removeStringAndMoveToFile($file, __DIR__ . '/dead.txt', $proxy);
    $db->updateData($proxy, ['status' => 'port-closed'], false);
    echo $proxy . " port closed" . PHP_EOL;
  }
}

// filter open port from untested proxies

$all = $db->getUntestedProxies(500);
foreach ($all as $data) {
  if (!isValidProxy($data['proxy'])) {
    $db->remove($data['proxy']);
  } else {
    processProxy($data['proxy']);
  }
}
