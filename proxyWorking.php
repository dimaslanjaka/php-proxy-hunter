<?php

// proxies writer

require_once __DIR__ . '/func-proxy.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpProxyHunter\geoPlugin;
use PhpProxyHunter\ProxyDB;
use function Annexare\Countries\countries;

global $isCli;

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

$impl = implode(PHP_EOL, array_map(function ($item) {
  $item['type'] = strtoupper($item['type']);
  unset($item['id']);
  return implode('|', $item);
}, $working));

// Explode the input into an array of lines
$lines = explode("\n", $impl);

// Sort the lines alphabetically
sort($lines);

$impl = join("\n", $lines);

// write working proxies
file_put_contents(__DIR__ . '/working.txt', $impl);

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

$untested = countNonEmptyLines($fileUntested);
$dead = countNonEmptyLines(__DIR__ . '/dead.txt');
echo "total working proxies " . count($working) . PHP_EOL;
echo "total private proxies " . count($private) . PHP_EOL;
echo "total dead proxies $dead" . PHP_EOL;
echo "total untested proxies $untested" . PHP_EOL;

file_put_contents(__DIR__ . '/status.json', json_encode(['working' => count($working), 'dead' => $dead, 'untested' => $untested, 'private' => count($private)]));


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
