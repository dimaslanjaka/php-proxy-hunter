<?php

// index all proxies into database

require_once __DIR__ . "/func.php";

use PhpProxyHunter\ProxyDB;

$untested = file(__DIR__ . '/proxies.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$dead = file(__DIR__ . '/dead.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$db = new ProxyDB();

iterateProxies(array_unique(array_merge($dead, $untested)));

function iterateProxies(array $proxies, int $startIndex = 0, int $maxIterations = null)
{
  global $db;
  $totalProxies = count($proxies);
  $iterations = 0;
  for ($i = $startIndex; $i < $totalProxies; $i++) {
    $proxy = $proxies[$i];
    if (!is_string($proxy)) continue;
    echo "$proxy" . PHP_EOL;
    $sel  = $db->select($proxy);
    if (empty($sel)) {
      $db->add($proxy);
    }

    $iterations++;
    if ($maxIterations !== null && $iterations >= $maxIterations) {
      break; // Stop iterating if the maximum iterations limit is reached
    }
  }
}
