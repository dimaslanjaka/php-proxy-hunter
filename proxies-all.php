<?php

// index all proxies into database

require_once __DIR__ . "/func.php";

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');
if (!$isCli)
  exit('web server access disallowed');

use PhpProxyHunter\ProxyDB;

$untested = file(__DIR__ . '/proxies.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$dead = file(__DIR__ . '/dead.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$db = new ProxyDB();

iterateProxies(array_unique(array_merge($dead, $untested)));

function iterateProxies(array $proxies, int $startIndex = 0, int $maxIterations = null)
{
  global $db;
  $totalProxies = count($proxies);

  // Check if $startIndex is within the bounds of the array
  if ($startIndex < 0 || $startIndex >= $totalProxies) {
    echo "Invalid startIndex provided. Exiting function.";
    return;
  }

  $iterations = 0;
  for ($i = $startIndex; $i < $totalProxies; $i++) {
    // Check if the index $i exists in the array
    if (!array_key_exists($i, $proxies)) {
      echo "Index $i does not exist in the array." . PHP_EOL;
      continue;
    }

    $proxy = $proxies[$i];
    if (!is_string($proxy)) continue;

    $sel  = $db->select($proxy);
    if (empty($sel)) {
      echo "add $proxy" . PHP_EOL;
      // add proxy
      $db->add($proxy);
      // re-select proxy
      $sel  = $db->select($proxy);
    }
    if (is_null($sel[0]['status'])) {
      $db->updateStatus($proxy, 'untested');
    }

    $iterations++;
    if ($maxIterations !== null && $iterations >= $maxIterations) {
      break; // Stop iterating if the maximum iterations limit is reached
    }
  }
}
