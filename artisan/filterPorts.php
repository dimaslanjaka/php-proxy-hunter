<?php

// filter open ports only

// Define project root for reuse
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/php_backend/shared.php';

use PhpProxyHunter\Proxy;

global $proxy_db;

$isCli = is_cli();

if (!$isCli) {
  header('Content-Type:text/plain; charset=UTF-8');
  exit('web server access disallowed');
}

$isAdmin = false;
// admin indicator
$max_checks = 500;
// max proxies to be checked
$maxExecutionTime = 120;
// max 120s execution time

if ($isCli) {
  $options = getopt('p:m::', [
    'proxy:',
    'max::',
    'userId::',
    'lockFile::',
    'runner::',
    'admin::',
  ]);

  if (!empty($options['max'])) {
    $m = intval($options['max']);
    if ($m > 0) {
      $max_checks = $m;
    }
  }

  if (!empty($options['admin']) && $options['admin'] !== 'false') {
    $isAdmin          = true;
    $maxExecutionTime = 600;
    set_time_limit(0);
  }
}

$scriptName   = basename(__FILE__, '.php');
$lockFilePath = $projectRoot . '/tmp/runners/' . $scriptName . '.lock';
$statusFile   = $projectRoot . '/status.txt';

if (file_exists($lockFilePath) && !is_debug()) {
  exit(date(DATE_RFC3339) . " another process still running {$scriptName}" . PHP_EOL);
}

write_file($lockFilePath, date(DATE_RFC3339));
write_file($statusFile, 'filter-ports');

function filterPortsExitProcess(): void {
  global $lockFilePath, $statusFile;

  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
  write_file($statusFile, 'idle');
}

register_shutdown_function('filterPortsExitProcess');

$db   = $proxy_db;
$file = $projectRoot . '/proxies.txt';

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
    $read    = read_first_lines($file, $max_checks) ?: [];
    $proxies = extractProxies(implode("\n", $read), null, false);
    $proxies = array_merge($proxies, $db_data_map);
  } else {
    // prioritize untested proxies from database
    $proxies = $db_data_map;
  }

  shuffle($proxies);

  // Convert array to iterator
  $proxyIterator    = new ArrayIterator($proxies);
  $multipleIterator = new MultipleIterator(MultipleIterator::MIT_NEED_ALL);
  $multipleIterator->attachIterator($proxyIterator);

  foreach ($multipleIterator as $proxyInfo) {
    foreach ($proxyInfo as $proxy) {
      processProxy($proxy->proxy);
    }
  }
} catch (Exception $e) {
  echo 'fail extracting proxies ' . $e->getMessage() . PHP_EOL;
}

function processProxy($proxy): void {
  global $start_time, $file, $db, $maxExecutionTime, $projectRoot;

  // Check if execution time exceeds [n] seconds
  if (microtime(true) - $start_time > $maxExecutionTime) {
    return;
  }

  if (!isPortOpen($proxy)) {
    removeStringAndMoveToFile($file, $projectRoot . '/dead.txt', $proxy);
    $db->updateData($proxy, ['status' => 'port-closed'], false);
    echo $proxy . ' port closed' . PHP_EOL;
  }
}

// filter open port from untested proxies

$all = $db->getUntestedProxies(500);

foreach ($all as $data) {
  $proxyVal = $data['proxy'];

  if (!isValidProxy($proxyVal)) {
    $db->remove($proxyVal);
  } else {
    processProxy($proxyVal);
  }
}
