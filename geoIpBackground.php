<?php

require_once __DIR__ . '/func-proxy.php';


if (function_exists('header')) {
  // Allow from any origin
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: *');
  header('Access-Control-Allow-Methods: *');
  header('Content-Type: application/json; charset=utf-8');

  if (isset($_REQUEST['uid'])) {
    setUserId($_REQUEST['uid']);
  }
  // only allow user with Google Analytics cookie
  if (!isset($_COOKIE['_ga'])) {
    exit('Access Denied');
  }

  // check admin
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

// Run a long-running process in the background
$lock_files   = [];
$file         = __DIR__ . '/geoIp.php';
$output_file  = __DIR__ . '/proxyChecker.txt';
$pid_file     = __DIR__ . '/geoIpBackround.pid';
$lock_files[] = $pid_file;
setMultiPermissions([$file, $output_file, $pid_file]);
$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$cmd   = 'php ' . escapeshellarg($file);

$uid = getUserId();
$cmd .= ' --userId=' . escapeshellarg($uid);

if (isset($_REQUEST['proxy'])) {
  $cmd .= ' --str=' . escapeshellarg(rawurldecode($_REQUEST['proxy']));
}

// validate lock files
$lock_file    = tmp() . '/runners/geoIp.lock';
$lock_files[] = $lock_file;
if (file_exists($lock_file) && !$isAdmin) {
  exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
}

echo $cmd . "\n\n";

$cmd    = sprintf('%s > %s 2>&1 & echo $! >> %s', $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));
$runner = __DIR__ . '/tmp/runners/' . basename(__FILE__, '.php') . ($isWin ? '.bat' : '');
setMultiPermissions($runner);
write_file($runner, $cmd);

runBashOrBatch($runner);

function exitProcess()
{
  global $lock_files;
  foreach ($lock_files as $file) {
    delete_path($file);
  }
}

register_shutdown_function('exitProcess');
