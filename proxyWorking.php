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
  echo "another process still running" . PHP_EOL;
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

$counterUntested = $db->countUntestedProxies();

$arr = [
    'working' => $db->countWorkingProxies(),
    'dead' => $db->countDeadProxies(),
    'untested' => $counterUntested,
    'private' => $db->countPrivateProxies()
];

foreach ($arr as $key => $value) {
  echo "total $key $value proxies" . PHP_EOL;
}

file_put_contents(__DIR__ . '/status.json', json_encode($arr));

setFilePermissions([
    __DIR__ . '/status.json',
    __DIR__ . '/proxies.txt',
    __DIR__ . '/dead.txt',
    __DIR__ . '/working.txt',
    __DIR__ . '/working.json'
]);


/**
 * Find the index of an item in an array based on its 'proxy' value.
 *
 * @param array $array The array to search through.
 * @param string $proxy The value of the 'proxy' key to search for.
 * @return int|null The index of the found item, or null if not found.
 */
function findByProxy($array, $proxy)
{
  foreach ($array as $key => $item) {
    if ($item['proxy'] === $proxy) {
      return $key; // Return the index of the found item
    }
  }
  return null; // Proxy not found
}

function removeDuplicateObjectsByKey($array, $key)
{
  $uniqueValues = [];
  $uniqueObjects = [];

  foreach ($array as $object) {
    // Check if the value of the specified key already exists in the uniqueValues array
    if (!in_array($object[$key], $uniqueValues)) {
      // If not, add it to the uniqueValues array and add the object to the uniqueObjects array
      $uniqueValues[] = $object[$key];
      $uniqueObjects[] = $object;
    }
  }

  return $uniqueObjects;
}
