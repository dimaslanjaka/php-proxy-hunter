<?php

// Define project root for reuse
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/func.php';

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  header('Content-Type: text/plain; charset=utf-8');
  requires_captcha_verification();
}

// Run a long-running process in the background
$file        = $projectRoot . '/artisan/proxyWorking.php';
$output_file = $projectRoot . '/tmp/output-shell/proxyWorking.txt';
$pid_file    = $projectRoot . '/proxyWorking.pid';
setMultiPermissions([$file, $output_file, $pid_file]);
$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$cmd   = 'php ' . escapeshellarg($file);
// if ($isWin) {
//   $cmd = "start /B \"proxy_checker\" $cmd";
// }

$uid = getUserId();
$cmd .= ' --userId=' . escapeshellarg($uid);

// validate lock files
if (file_exists($projectRoot . '/proxyChecker.lock') && !is_debug()) {
  exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
}

echo $cmd . "\n\n";

$cmd    = sprintf('%s > %s 2>&1 & echo $! >> %s', $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));
$runner = $projectRoot . '/tmp/runners/' . basename(__FILE__, '.php') . ($isWin ? '.bat' : '');

write_file($runner, $cmd);

runBashOrBatch($runner);

function exitProcess(): void {
  global $pid_file;
  if (file_exists($pid_file)) {
    unlink($pid_file);
  }
}

register_shutdown_function('exitProcess');
