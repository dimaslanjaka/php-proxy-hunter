<?php

declare(strict_types=1);

require_once __DIR__ . '/../php_backend/shared.php';

use PhpProxyHunter\Server;

global $proxy_db, $isAdmin, $isCli;

// Define project root for reuse
$projectRoot = dirname(__DIR__);

$isCli = is_cli();
$uid   = getUserId();

if (!$isCli) {
  Server::allowCors(true);
  header('Content-Type:text/plain; charset=UTF-8');

  // Run this script in background using same PHP executable
  $phpBin = 'php' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');
  $script = $projectRoot . '/artisan/proxyWorking.php';
  $cmd    = $phpBin . ' ' . escapeshellarg($script);
  $cmd .= ' --userId=' . escapeshellarg($uid);
  $cmd .= ' --admin=' . escapeshellarg($isAdmin ? 'true' : 'false');
  $output_file = tmp() . '/logs/user-' . $uid . '/proxyWorking.log';
  $pid_file    = $projectRoot . '/tmp/runners/proxyWorking-' . $uid . '.pid';

  $runner = $projectRoot . '/tmp/runners/' . 'proxyWorking' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.bat' : '');
  ensure_dir(dirname($output_file));
  $cmd = sprintf('%s > %s 2>&1 & echo $! >> %s', $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));
  write_file($runner, $cmd);

  runBashOrBatch($runner);

  echo $cmd . "\n\n";

  echo "Started proxyWorking.php in background. Check log file at: {$output_file}" . PHP_EOL;
  exit;
}

if (file_exists($projectRoot . '/proxyChecker.lock') && !is_debug()) {
  exit('proxy checker process still running');
}

$lockFilePath = tmp() . '/locks/user-' . $uid . '/artisan/proxyWorking.lock';

if (file_exists($lockFilePath) && !is_debug() && !$isAdmin) {
  echo date(DATE_RFC3339) . ' another process still running' . PHP_EOL;
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
}

function exitProcess(): void
{
  global $lockFilePath;
  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
}

register_shutdown_function('exitProcess');

$workingData = writing_working_proxies_file($proxy_db, tmp() . '/locks/user-' . $uid . '/artisan/proxyWorking-writer.lock');

echo PHP_EOL;

// print working proxies [protocols]://IP:PORT@username:password
$proxies = $proxy_db->getWorkingProxies();
$proxies = array_map(function ($item) {
  $raw = $item['type'] . '://' . $item['proxy'];
  if (!empty($raw['username']) && !empty($raw['password'])) {
    $raw .= '@' . $raw['username'] . ':' . $raw['password'];
  }
  return $raw;
}, $proxies);

// print proxies

echo implode(PHP_EOL, $proxies) . PHP_EOL . PHP_EOL;

foreach ($workingData['counter'] as $key => $value) {
  echo "total $key $value proxies" . PHP_EOL;
}

setMultiPermissions([
  $projectRoot . '/status.json',
  $projectRoot . '/proxies.txt',
  $projectRoot . '/dead.txt',
  $projectRoot . '/working.txt',
  $projectRoot . '/working.json',
]);
