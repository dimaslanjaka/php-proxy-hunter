<?php

require_once __DIR__ . '/../php_backend/shared.php';

use PhpProxyHunter\Server;

global $proxy_db;

$isCli          = is_cli();
$rootProjectDir = realpath(__DIR__ . '/..');

if (!$isCli) {
  Server::allowCors(true);
  header('Content-Type: text/plain; charset=utf-8');
  if (isset($_REQUEST['uid'])) {
    setUserId($_REQUEST['uid']);
  }
  // only allow user with Google Analytics cookie
  if (!isset($_COOKIE['_ga'])) {
    exit('Access Denied');
  }
}

$proxiesToCheck = [];

// Get untested proxies only
$proxiesToCheck = $proxy_db->getUntestedProxies(100);
if (empty($proxiesToCheck)) {
  // Get oldest tested proxies
  $proxiesToCheck = $proxy_db->getOldestTestedProxies(100);
}
if (empty($proxiesToCheck)) {
  exit('No proxies to check' . PHP_EOL);
}

// Shuffle proxies to distribute load'
shuffle($proxiesToCheck);

// Get first [n] proxies to check
$proxiesToCheck = array_slice($proxiesToCheck, 0, 5);

foreach ($proxiesToCheck as $proxy) {
  $proccessId = md5($proxy['proxy']);
  // Run a long-running process in the background
  $file        = realpath($rootProjectDir . '/artisan/proxyChecker.php');
  $pid_file    = $rootProjectDir . '/tmp/runners/proxyChecker' . $proccessId . '.pid';
  $output_file = $rootProjectDir . '/tmp/logs/proxyChecker/' . $proccessId . '.txt';
  $lockFile    = $rootProjectDir . '/tmp/locks/proxyChecker/' . $proccessId . '.lock';
  // detect platform early so runner filename can include proper extension
  $isWin  = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  $runner = $rootProjectDir . '/tmp/runners/proxyChecker/' . $proccessId . ($isWin ? '.bat' : '');
  ensure_dir(dirname($output_file));
  ensure_dir(dirname($pid_file));
  setMultiPermissions([$file, $output_file, $pid_file], true);

  $cmd = 'php ' . escapeshellarg($file);
  $uid = getUserId();
  $cmd .= ' --userId=' . escapeshellarg($uid);
  $cmd .= ' --pidFile=' . escapeshellarg($pid_file);
  $cmd .= ' --outputFile=' . escapeshellarg($output_file);
  $cmd .= ' --runnerFile=' . escapeshellarg($runner);
  $cmd .= ' --lockFile=' . escapeshellarg($lockFile);
  if (!empty($proxy['username']) && !empty($proxy['password'])) {
    $cmd .= ' --username=' . escapeshellarg($proxy['username']);
    $cmd .= ' --password=' . escapeshellarg($proxy['password']);
    $cmd .= ' --proxy=' . escapeshellarg($proxy['proxy'] . '@' . $proxy['username'] . ':' . $proxy['password']);
  } else {
    $cmd .= ' --proxy=' . escapeshellarg($proxy['proxy']);
  }

  // validate lock files
  if (file_exists($lockFile) && !is_debug()) {
    exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
  }

  $cmd = trim($cmd);

  // run in background without waiting
  if ($isWin) {
    // Windows: use cmd /C start "" /B ... to detach process, redirect stdout/stderr
    $background = 'cmd /C start "" /B ' . $cmd . ' > ' . escapeshellarg($output_file) . ' 2>&1';
    // popen + pclose returns immediately
    @pclose(@popen($background, 'r'));
  } else {
    // Unix: background the process and redirect output
    $background = $cmd . ' > ' . escapeshellarg($output_file) . ' 2>&1 &';
    @exec($background);
  }
}
