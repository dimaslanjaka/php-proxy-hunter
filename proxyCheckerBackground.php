<?php

require_once __DIR__ . "/func.php";

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
}

// Run a long-running process in the background
$file = __DIR__ . "/proxyChecker.php";
$outputfile = __DIR__ . '/proxyChecker.txt';
$pidfile = __DIR__ . '/proxyChecker.pid';
setFilePermissions([$file, $outputfile, $pidfile]);
$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$cmd = "start /B php $file";
if (!$isWin) {
  $cmd = "php $file";
}
$cmd .= " --userId=" . getUserId();

echo $cmd . "\n\n";

// if ($isWin) {
//   // exec("start /B php $file > proxyChecker.txt 2>&1");
//   // exec("start /B cmd.exe /C \"php $file > proxyChecker.txt 2>&1\"");

//   exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
// } else {
//   exec("php $file > proxyChecker.txt 2>&1 &");
// }

exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
