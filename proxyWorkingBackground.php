<?php

require_once __DIR__ . "/func.php";

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  header('Content-Type: text/plain; charset=utf-8');
  setCacheHeaders(5);
  if (isset($_REQUEST['uid'])) {
    setUserId($_REQUEST['uid']);
  }
  // only allow user with Google Analytics cookie
  if (!isset($_COOKIE['_ga'])) {
    exit('Access Denied');
  }
}

// Run a long-running process in the background
$file = __DIR__ . "/proxyWorking.php";
$output_file = __DIR__ . '/tmp/proxyWorking.txt';
$pid_file = __DIR__ . '/proxyWorking.pid';
setMultiPermissions([$file, $output_file, $pid_file]);
$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$cmd = "php " . escapeshellarg($file);
if ($isWin) {
  $cmd = "start /B \"proxy_checker\" $cmd";
}

$uid = getUserId();
$cmd .= " --userId=" . escapeshellarg($uid);

// validate lock files
if (file_exists(__DIR__ . '/proxyChecker.lock') && !is_debug()) {
  exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
}

echo $cmd . "\n\n";

$cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));
if (!file_exists(__DIR__ . '/tmp')) {
  mkdir(__DIR__ . '/tmp');
}
$runner = __DIR__ . "/tmp/runners/" . md5(__FILE__) . ($isWin ? '.bat' : "");
setMultiPermissions($runner);
file_put_contents($runner, $cmd);

exec(escapeshellarg($runner));

function exitProcess()
{
  global $pid_file;
  if (file_exists($pid_file)) {
    unlink($pid_file);
  }
}

register_shutdown_function('exitProcess');
