<?php

require_once __DIR__ . '/func.php';

use PhpProxyHunter\Scheduler;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  // Allow from any origin
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: *');
  header('Access-Control-Allow-Methods: *');
  header('Content-Type: text/plain; charset=utf-8');
  if (isset($_REQUEST['uid'])) {
    setUserId($_REQUEST['uid']);
  }
  // only allow user with Google Analytics cookie
  if (!isset($_COOKIE['_ga'])) {
    exit('Access Denied');
  }
}

// Run a long-running process in the background
$file        = realpath(__DIR__ . '/proxyChecker.php');
$output_file = __DIR__ . '/proxyChecker.txt';
$pid_file    = __DIR__ . '/tmp/runners/proxyChecker.pid';
setMultiPermissions([$file, $output_file, $pid_file], true);
$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$cmd   = 'php ' . escapeshellarg($file);

$uid = getUserId();
$cmd .= ' --userId=' . escapeshellarg($uid);

// validate lock files
if (file_exists(__DIR__ . '/proxyChecker.lock') && !is_debug()) {
  exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
}

$cmd = trim($cmd);

echo $cmd . "\n\n";

$cmd    = sprintf('%s > %s 2>&1 & echo $! >> %s', $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));
$runner = __DIR__ . '/tmp/runners/proxyChecker' . ($isWin ? '.bat' : '');

write_file($runner, $cmd);

runBashOrBatch($runner);

Scheduler::register(function () {
  global $pid_file;
  if (file_exists($pid_file)) {
    unlink($pid_file);
  }
}, 'zExit');
