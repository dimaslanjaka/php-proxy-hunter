<?php

// proxies writer

require_once __DIR__ . '/func-proxy.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpProxyHunter\geoPlugin;
use PhpProxyHunter\ProxyDB;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

// if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');
if (!$isCli)
  exit('web server access disallowed');

if (gethostname() !== 'DESKTOP-JVTSJ6I') {
  if (file_exists(__DIR__ . '/proxyChecker.lock')) {
    exit('another process still running');
  }
}

// remove duplicated lines from proxies.txt compare with dead.txt
$file1 = realpath(__DIR__ . "/proxies.txt");
$file2 = realpath(__DIR__ . "/dead.txt");

// Get duplicated lines
$duplicatedLines = getDuplicatedLines($file1, $file2);

if (!empty($duplicatedLines)) {
  // Output duplicated lines
  echo "Duplicated lines between $file1 and $file2:\n";
  foreach ($duplicatedLines as $line) {
    echo trim($line) . PHP_EOL;
  }

  // Remove duplicated lines from $file1
  $lines1 = file($file1);
  $lines1 = array_diff($lines1, $duplicatedLines);
  file_put_contents($file1, implode("", $lines1));
}

$geo_plugin = new geoPlugin();
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
  if (empty($item['useragent'])) {
    $item['useragent'] = randomWindowsUa();
    $db->updateData($item['proxy'], $item);
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

//if (!$isCli) {
//  if (!isset($_COOKIE['rewrite_cookies'])) {
//    // Set the cookie to expire in 5 minutes (300 seconds)
//    $cookie_value = md5(date(DATE_RFC3339));
//    setcookie('rewrite_cookies', $cookie_value, time() + 300, '/');
//
//    // rewrite working proxies to be checked again later
//    file_put_contents($fileUntested, PHP_EOL . $impl . PHP_EOL, FILE_APPEND);
//    // filter only IP:PORT each lines
//    rewriteIpPortFile($fileUntested);
//    // remove duplicate proxies
//    removeDuplicateLines($fileUntested);
//  }
//}

$untested = extractProxies(file_get_contents($fileUntested));
$dead = countNonEmptyLines(__DIR__ . '/dead.txt');
echo "total working proxies " . count($working) . PHP_EOL;
echo "total private proxies " . count($private) . PHP_EOL;
echo "total dead proxies $dead" . PHP_EOL;
echo "total untested proxies ". count($untested) . PHP_EOL;

file_put_contents(__DIR__ . '/status.json', json_encode(['working' => count($working), 'dead' => $dead, 'untested' => count($untested), 'private' => count($private)]));


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
