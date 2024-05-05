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
$working = $db->getWorkingProxies();
$private = $db->getPrivateProxies();
usort($working, function ($a, $b) {
  return strtotime($b['last_check']) - strtotime($a['last_check']);
});
$array_mapper = array_map(function ($item) use ($db) {
  foreach ($item as $key => $value) {
    if (empty($value)) {
      $item[$key] = '-';
    }
  }

  $item['type'] = strtoupper($item['type']);
  unset($item['id']);
  if (empty($item['useragent']) && strlen(trim($item['useragent'])) <= 5) {
    $item['useragent'] = randomWindowsUa();
    $db->updateData($item['proxy'], $item);
    get_geo_ip($item['proxy']);
  }
  return $item;
}, $working);
$impl = array_map(function ($item) {
  return implode('|', $item);
}, $array_mapper);
$impl = implode(PHP_EOL, $impl);

// write working proxies
file_put_contents(__DIR__ . '/working.txt', $impl);
file_put_contents(__DIR__ . '/working.json', json_encode($array_mapper));

$fileUntested = __DIR__ . '/proxies.txt';

$untested = extractProxies(file_get_contents($fileUntested));
$untested = uniqueClassObjectsByProperty($untested, 'proxy');
$dead = countNonEmptyLines(__DIR__ . '/dead.txt');
$arr = [
  'working' => count($working),
  'dead' => $dead,
  'untested' => count($untested),
  'private' => count($private)
];

foreach ($arr as $key => $value) {
  echo "working $key $value proxies" . PHP_EOL;
}

file_put_contents(__DIR__ . '/status.json', json_encode($arr));


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
