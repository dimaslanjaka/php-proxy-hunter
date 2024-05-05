<?php

require_once __DIR__ . "/func-proxy.php";

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
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
$file = __DIR__ . "/filterPorts.php";
$output_file = __DIR__ . '/proxyChecker.txt';
$pid_file = __DIR__ . '/filterPorts.pid';
setFilePermissions([$file, $output_file, $pid_file]);
$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$cmd = "php " . escapeshellarg($file);
if ($isWin) {
  $cmd = "start /B \"filter_ports\" $cmd";
}

$uid = getUserId();
$cmd .= " --userId=" . escapeshellarg($uid);

// validate lock files
if (file_exists(__DIR__ . '/proxyChecker.lock') || file_exists(__DIR__ . '/proxySocksChecker.lock')) {
  exit('Another process still running');
}

echo $cmd . "\n\n";

$cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));
if (!file_exists(__DIR__ . '/tmp')) mkdir(__DIR__ . '/tmp');
$runner = __DIR__ . "/tmp/runner_" . md5(__FILE__) . ($isWin ? '.bat' : "");
setFilePermissions($runner);
file_put_contents($runner, $cmd);

exec(escapeshellarg($runner));

function exitProcess()
{
  global $pid_file;
  if (file_exists($pid_file)) unlink($pid_file);
}
register_shutdown_function('exitProcess');
