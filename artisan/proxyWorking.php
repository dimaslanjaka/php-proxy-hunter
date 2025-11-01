<?php

declare(strict_types=1);

// Define project root for reuse
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/php_backend/shared.php';

global $proxy_db;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  header('Content-Type:text/plain; charset=UTF-8');
}
if (!$isCli) {
  exit('web server access disallowed');
}

if (file_exists($projectRoot . '/proxyChecker.lock') && !is_debug()) {
  exit('proxy checker process still running');
}

$lockFilePath = $projectRoot . '/proxyWorking.lock';

if (file_exists($lockFilePath) && !is_debug()) {
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

// use the global proxy DB instance directly
$data = parse_working_proxies($proxy_db);

// write working proxies
write_file($projectRoot . '/working.txt', $data['txt']);
write_file($projectRoot . '/working.json', json_encode($data['array']));
write_file($projectRoot . '/status.json', json_encode($data['counter']));

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

foreach ($data['counter'] as $key => $value) {
  echo "total $key $value proxies" . PHP_EOL;
}

setMultiPermissions([
  $projectRoot . '/status.json',
  $projectRoot . '/proxies.txt',
  $projectRoot . '/dead.txt',
  $projectRoot . '/working.txt',
  $projectRoot . '/working.json',
]);
