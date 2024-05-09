<?php

/** @noinspection DuplicatedCode */

// working proxies writer

require_once __DIR__ . '/func-proxy.php';

use PhpProxyHunter\ProxyDB;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');
if (!$isCli)
  exit('web server access disallowed');

if (file_exists(__DIR__ . '/proxyChecker.lock') && gethostname() !== 'DESKTOP-JVTSJ6I') {
  exit('proxy checker process still running');
}

$lockFilePath = __DIR__ . "/proxyWorking.lock";

if (file_exists($lockFilePath) && gethostname() !== 'DESKTOP-JVTSJ6I') {
  echo "another process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
}

function exitProcess()
{
  global $lockFilePath;
  if (file_exists($lockFilePath))
    unlink($lockFilePath);
}

register_shutdown_function('exitProcess');

$db = new ProxyDB();
$data = parse_working_proxies($db);

// write working proxies
file_put_contents(__DIR__ . '/working.txt', $data['txt']);
file_put_contents(__DIR__ . '/working.json', json_encode($data['array']));

foreach ($data['counter'] as $key => $value) {
  echo "total $key $value proxies" . PHP_EOL;
}

file_put_contents(__DIR__ . '/status.json', json_encode($data['counter']));

setFilePermissions([
    __DIR__ . '/status.json',
    __DIR__ . '/proxies.txt',
    __DIR__ . '/dead.txt',
    __DIR__ . '/working.txt',
    __DIR__ . '/working.json'
]);
