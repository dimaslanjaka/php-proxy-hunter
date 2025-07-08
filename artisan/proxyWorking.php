<?php

declare(strict_types=1);

/** @noinspection DuplicatedCode */

// working proxies writer

require_once dirname(__DIR__) . '/func-proxy.php';

use PhpProxyHunter\ProxyDB;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  header('Content-Type:text/plain; charset=UTF-8');
}
if (!$isCli) {
  exit('web server access disallowed');
}

$projectDir = dirname(__DIR__);
if (file_exists($projectDir . '/proxyChecker.lock') && !is_debug()) {
  exit('proxy checker process still running');
}

$lockFilePath = $projectDir . '/proxyWorking.lock';

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

$db = new ProxyDB();
$data = parse_working_proxies($db);

// write working proxies
write_file($projectDir . '/working.txt', $data['txt']);
write_file($projectDir . '/working.json', json_encode($data['array']));

// write status
write_file($projectDir . '/status.json', json_encode($data['counter']));

echo PHP_EOL;

// print working proxies [protocols]://IP:PORT@username:password
$proxies = $db->getWorkingProxies();
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

// set limitation for below codes only
//if (function_exists('set_time_limit')) set_time_limit(30);
//
//$untested = extractProxies(read_file(__DIR__ . '/proxies.txt'));
//$untested = uniqueClassObjectsByProperty($untested, 'proxy');
//$untested = count($untested);
//echo "total untested proxies from file " . $untested . PHP_EOL;
//$data['counter']['untested'] += $untested;
//file_put_contents(__DIR__ . '/status.json', json_encode($data['counter']));

setMultiPermissions([
  $projectDir . '/status.json',
  $projectDir . '/proxies.txt',
  $projectDir . '/dead.txt',
  $projectDir . '/working.txt',
  $projectDir . '/working.json',
]);
