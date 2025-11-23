<?php

require_once __DIR__ . '/../php_backend/shared.php';

use PhpProxyHunter\CoreDB;
use PhpProxyHunter\Server;

global $isAdmin, $dbFile, $dbHost, $dbName, $dbUser, $dbPass, $dbType;

// -----------------------------------------------------------------------------
// Init
// -----------------------------------------------------------------------------

$projectRoot = dirname(__DIR__);
$isCli       = is_cli();
$uid         = getUserId();

// DB init
$core_db  = new CoreDB($dbFile, $dbHost, $dbName, $dbUser, $dbPass, false, $dbType);
$proxy_db = $core_db->proxy_db;

$lockDir      = tmp() . '/locks';
$lockFilePath = "$lockDir/proxyWorking.lock";
$writerLock   = "$lockDir/proxyWorking-writer.lock";

// -----------------------------------------------------------------------------
// Non-CLI mode → Launch background worker
// -----------------------------------------------------------------------------

if (!$isCli) {
  Server::allowCors(true);
  header('Content-Type: text/plain; charset=UTF-8');

  $phpBinary = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
  $script    = escapeshellarg(__FILE__);

  $outputFile = tmp() . "/logs/user-$uid/proxyWorking.log";
  $pidFile    = "$projectRoot/tmp/runners/proxyWorking-$uid.pid";
  $runnerFile = "$projectRoot/tmp/runners/proxyWorking" . (PHP_OS_FAMILY === 'Windows' ? '.bat' : '.sh');

  write_file($outputFile, '[' . date('Y-m-d H:i:s') . "] Starting proxyWorking process...\n");
  write_file($pidFile, '[' . date('Y-m-d H:i:s') . "] PID log for proxyWorking process:\n");

  $cmd = sprintf(
    '%s %s --userId=%s --admin=%s > %s 2>&1 & echo $! >> %s',
    $phpBinary,
    $script,
    escapeshellarg($uid),
    escapeshellarg($isAdmin ? 'true' : 'false'),
    escapeshellarg($outputFile),
    escapeshellarg($pidFile)
  );

  write_file($runnerFile, $cmd);
  runBashOrBatch($runnerFile);

  echo $cmd . PHP_EOL . PHP_EOL;
  echo "Started proxyWorking.php in background. Log: $outputFile" . PHP_EOL;
  exit;
}

// -----------------------------------------------------------------------------
// Process Locking
// -----------------------------------------------------------------------------

// Prevent duplication when proxyChecker is running
if (file_exists("$projectRoot/proxyChecker.lock") && !is_debug()) {
  exit('proxy checker process still running');
}

// Prevent double-execution
if (file_exists($lockFilePath) && !is_debug() && !$isAdmin) {
  echo date(DATE_RFC3339) . ' another process still running' . PHP_EOL;
  exit;
}

file_put_contents($lockFilePath, date(DATE_RFC3339));

// Auto-remove lock on exit
register_shutdown_function(function () use ($lockFilePath) {
  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
});

// -----------------------------------------------------------------------------
// Run proxy generation
// -----------------------------------------------------------------------------

$workingData = writing_working_proxies_file($proxy_db, $writerLock);

echo PHP_EOL;

foreach ($workingData['counter'] as $key => $value) {
  echo "total $key $value proxies" . PHP_EOL;
}

// -----------------------------------------------------------------------------
// Permissions
// -----------------------------------------------------------------------------

setMultiPermissions([
  "$projectRoot/status.json",
  "$projectRoot/proxies.txt",
  "$projectRoot/dead.txt",
  "$projectRoot/working.txt",
  "$projectRoot/working.json",
]);

// Optional: delay lock release if needed
// sleep(10);
