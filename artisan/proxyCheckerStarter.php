<?php

require_once __DIR__ . '/../php_backend/shared.php';

use PhpProxyHunter\Server;

global $proxy_db;

$isCli          = is_cli();
$rootProjectDir = realpath(__DIR__ . '/..');

if (!$isCli) {
  Server::allowCors(true);
  header('Content-Type: text/plain; charset=utf-8');

  $request = parseQueryOrPostBody();
  if (isset($request['uid'])) {
    setUserId($request['uid']);
  }

  if (empty($_SESSION['captcha']) || !$_SESSION['captcha']) {
    exit('Access Denied');
  }
}

$userId = getUserId();

if (!$isCli) {
  $sessionKey = 'lastProxyCheck-' . $userId;
  $lastCheck  = $_SESSION[$sessionKey] ?? 0;
  $now        = time();

  if ($now - $lastCheck < 300) {
    exit("Proxy check was run less than 5 minutes ago. Please wait.\n");
  }

  $_SESSION[$sessionKey] = $now;
}

// Try untested proxies first, fallback to oldest tested
$proxiesToCheck = $proxy_db->getUntestedProxies(100);

if (empty($proxiesToCheck)) {
  $proxiesToCheck = $proxy_db->getOldestTestedProxies(100);
}

if (empty($proxiesToCheck)) {
  exit("No proxies to check\n");
}

shuffle($proxiesToCheck);
$proxiesToCheck = array_slice($proxiesToCheck, 0, 5);

$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

foreach ($proxiesToCheck as $proxy) {
  $proxyStr = $proxy['proxy'] ?? '';
  if ($proxyStr === '') {
    continue;
  }

  $processId = md5($proxyStr);

  $runnerDir = $rootProjectDir . '/tmp/runners/proxyChecker/';
  $logDir    = $rootProjectDir . '/tmp/logs/proxyChecker/';
  $lockDir   = $rootProjectDir . '/tmp/locks/proxyChecker/';

  $pidFile    = $runnerDir . $processId . '.pid';
  $outputFile = $logDir . $processId . '.txt';
  $lockFile   = $lockDir . $processId . '.lock';
  $runnerFile = $runnerDir . $processId . ($isWin ? '.bat' : '.sh');

  $checkerFile = realpath($rootProjectDir . '/artisan/proxyChecker.php');

  // Prepare files
  write_file($pidFile, '');
  write_file($outputFile, '');
  write_file($lockFile, '');
  setMultiPermissions([$checkerFile, $outputFile, $pidFile], true);

  if (file_exists($lockFile) && !is_debug()) {
    exit(date(DATE_RFC3339) . " another process still running\n");
  }

  // Build command
  $cmdParts = [
    'php ' . escapeshellarg($checkerFile),
    '--userId=' . escapeshellarg($userId),
    '--pidFile=' . escapeshellarg($pidFile),
    '--outputFile=' . escapeshellarg($outputFile),
    '--runnerFile=' . escapeshellarg($runnerFile),
    '--lockFile=' . escapeshellarg($lockFile),
  ];

  if (!empty($proxy['username']) && !empty($proxy['password'])) {
    $user       = $proxy['username'];
    $pass       = $proxy['password'];
    $cmdParts[] = '--username=' . escapeshellarg($user);
    $cmdParts[] = '--password=' . escapeshellarg($pass);
    if (!empty($user) && !empty($pass)) {
      $cmdParts[] = '--proxy=' . escapeshellarg($proxyStr . '@' . $user . ':' . $pass);
    } else {
      $cmdParts[] = '--proxy=' . escapeshellarg($proxyStr);
    }
  } else {
    $cmdParts[] = '--proxy=' . escapeshellarg($proxyStr);
  }

  $cmd = trim(implode(' ', $cmdParts));

  // Run background process
  if ($isWin) {
    $background = 'cmd /C start "" /B ' . $cmd . ' > ' . escapeshellarg($outputFile) . ' 2>&1';
    @pclose(@popen($background, 'r'));
  } else {
    @exec($cmd . ' > ' . escapeshellarg($outputFile) . ' 2>&1 &');
  }
}

// Run filterPorts after starting proxy checks
$filterPortsScript   = realpath(__DIR__ . '/filterPorts.php');
$filterPortsLockFile = $rootProjectDir . '/tmp/runners/filterPorts.lock';
if ($isWin) {
  $background = 'cmd /C start "" /B php ' . escapeshellarg($filterPortsScript) . ' --lockFile=' . escapeshellarg($filterPortsLockFile) . ' > NUL 2>&1';
  @pclose(@popen($background, 'r'));
} else {
  @exec('php ' . escapeshellarg($filterPortsScript) . ' --lockFile=' . escapeshellarg($filterPortsLockFile) . ' > /dev/null 2>&1 &');
}

// Run filterPortsDuplicates after starting proxy checks
$filterPortsDupScript   = realpath(__DIR__ . '/filterPortsDuplicate.php');
$filterPortsDupLockFile = $rootProjectDir . '/tmp/runners/filterPortsDuplicate.lock';
if ($isWin) {
  $background = 'cmd /C start "" /B php ' . escapeshellarg($filterPortsDupScript) . ' --lockFile=' . escapeshellarg($filterPortsDupLockFile) . ' > NUL 2>&1';
  @pclose(@popen($background, 'r'));
} else {
  @exec('php ' . escapeshellarg($filterPortsDupScript) . ' --lockFile=' . escapeshellarg($filterPortsDupLockFile) . ' > /dev/null 2>&1 &');
}
