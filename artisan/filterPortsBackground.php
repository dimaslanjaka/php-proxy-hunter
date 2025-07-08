<?php

declare(strict_types=1);

// Define project root for reuse
$projectRoot = dirname(__DIR__);

require_once $projectRoot . "/func-proxy.php";

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

// Run a long-running process in the background
$files = [$projectRoot . "/artisan/filterPorts.php", $projectRoot . "/artisan/filterPortsDuplicate.php"];
$lock_files = [];
$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

foreach ($files as $file) {
  $output_file = $projectRoot . '/proxyChecker.txt';
  $pid_file = $projectRoot . '/tmp/runners/' . basename($file, '.php') . '.pid';
  setMultiPermissions([$file, $output_file, $pid_file]);
  $cmd = "php " . escapeshellarg($file);

  $uid = getUserId();
  $cmd .= " --userId=" . escapeshellarg($uid);
  $cmd .= " --max=" . escapeshellarg("30");
  $cmd .= " --admin=" . escapeshellarg($isAdmin ? 'true' : 'false');

  // validate lock files
  $lock_file = $projectRoot . '/tmp/runners/' . basename($file, '.php') . '.lock';
  $lock_files[] = $lock_file;
  if (file_exists($lock_file) && !is_debug()) {
    echo date(DATE_RFC3339) . ' another process still running '  . basename($file, '.php') . PHP_EOL;
    continue;
  }

  echo $cmd . "\n\n";

  $cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));

  $runner = $projectRoot . "/tmp/runners/" . basename(__FILE__, '.php') . ($isWin ? '.bat' : "");
  write_file($runner, $cmd);
  write_file($lock_file, '');

  runBashOrBatch($runner);
}

function exitProcess()
{
  global $lock_files;
  foreach ($lock_files as $file) {
    delete_path($file);
  }
}
register_shutdown_function('exitProcess');
