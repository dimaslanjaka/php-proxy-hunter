<?php

// run CIDR-check.php


require_once __DIR__ . "/../func-proxy.php";

$isAdmin = is_debug();

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
  // check admin
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

$file = realpath(__DIR__ . '/CIDR-check.php');
$lock_files = [];
$output_file = __DIR__ . '/../proxyChecker.txt';
$pid_file = tmp() . '/runners/' . md5($file) . '.pid';
$cmd = "php " . escapeshellarg($file);
$uid = getUserId();
$cmd .= " --userId=" . escapeshellarg($uid);
$cmd .= " --max=" . escapeshellarg("30");
$cmd .= " --admin=" . escapeshellarg($isAdmin ? 'true' : 'false');

// validate lock files
$lock_file = tmp() . '/runners/' . md5($file) . '.lock';
$lock_files[] = $lock_file;
if (file_exists($lock_file) && !is_debug()) {
  exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
}

echo $cmd . "\n\n";

$cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));

$runner = tmp() . "/runners/" . md5(__FILE__) . ($isWin ? '.bat' : "");
write_file($runner, $cmd);
write_file($lock_file, '');

exec(escapeshellarg($runner));

// remove lock files on exit
function exitProcess()
{
  global $lock_files;
  foreach ($lock_files as $file) {
    delete_path($file);
  }
}
register_shutdown_function('exitProcess');
