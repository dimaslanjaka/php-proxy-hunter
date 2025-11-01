<?php

require_once __DIR__ . '/func-proxy.php';

use PhpProxyHunter\Server;

if (!is_cli()) {
  Server::allowCors(true);

  // Set content type to plain text with UTF-8 encoding
  header('Content-Type: text/plain; charset=utf-8');

  if (isset($_REQUEST['uid'])) {
    setUserId($_REQUEST['uid']);
  }

  if (empty($_SESSION['captcha'])) {
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

$request = parseQueryOrPostBody();
if (isset($request['proxy'])) {
  $cmd .= ' --str=' . escapeshellarg(rawurldecode($request['proxy']));
} else {
  $opt = getopt('', ['str:']);
  if (isset($opt['str'])) {
    $cmd .= ' --str=' . escapeshellarg(rawurldecode($opt['str']));
  }
}

// validate lock files
$lock_file    = tmp() . '/locks/geoIp' . $uid . '.lock';
$lock_files[] = $lock_file;
if (file_exists($lock_file) && !$isAdmin) {
  exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
}

echo $cmd . "\n\n";

// Run command in background

// prepare runner/output dirs and runner file
$runner = tmp() . '/runners/geoIp' . $uid . ($isWin ? '.bat' : '.sh');
ensure_dir(dirname($output_file));
ensure_dir(dirname($pid_file));
ensure_dir(dirname($lock_file));
ensure_dir(dirname($runner));
setMultiPermissions([$file, $output_file, $pid_file, $runner]);

$cmd = trim($cmd);

// create a small runner wrapper so the detached process can be started reliably
if ($isWin) {
  $runner_content = "@echo off\r\n" . $cmd . ' > ' . escapeshellarg($output_file) . " 2>&1\r\n";
  @file_put_contents($runner, $runner_content);
} else {
  $runner_content = "#!/bin/sh\n" . $cmd . ' > ' . escapeshellarg($output_file) . " 2>&1\n";
  @file_put_contents($runner, $runner_content);
  @chmod($runner, 0755);
}

// run in background without waiting
if ($isWin) {
  // Windows: use cmd /C start "" /B to detach process
  $background = 'cmd /C start "" /B ' . escapeshellarg($runner);
  @pclose(@popen($background, 'r'));
} else {
  // Unix: execute runner script in background
  $background = escapeshellarg($runner) . ' > /dev/null 2>&1 &';
  @exec($background);
}

function exitProcess()
{
  global $lock_files;
  foreach ($lock_files as $file) {
    delete_path($file);
  }
}

register_shutdown_function('exitProcess');
