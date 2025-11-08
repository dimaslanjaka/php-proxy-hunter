<?php

require_once __DIR__ . '/../../../php_backend/shared.php';

global $proxy_db;

use PhpProxyHunter\Checker\ProxyChecker1;
use PhpProxyHunter\Checker\ProxyCheckerHttpOnly;
use PhpProxyHunter\Checker\CheckerOptions;

$proxy = '72.206.74.126:4145';

$options = [
  'proxy'     => $proxy,
  'protocols' => ['http', 'https', 'socks4', 'socks5', 'socks4a', 'socks5h'],
  'timeout'   => 10,
  'verbose'   => true,
];

// Run both checkers with shared options
$results = [
  'full_check' => ProxyChecker1::check(new CheckerOptions($options)),
  'http_only'  => ProxyCheckerHttpOnly::check(new CheckerOptions($options)),
];

// Output neatly
foreach ($results as $type => $result) {
  echo strtoupper($type) . " RESULT:\n";
  var_dump($result);
  echo "\n";
}

// Save to database
// work when all result is working
if ($results['full_check']->isWorking || $results['http_only']->isWorking) {
  $proxy_db->updateData($proxy, [
    'https'      => $results['full_check']->isSSL ? 'true' : 'false',
    'anonymity'  => $results['full_check']->anonymity ?: $results['http_only']->anonymity,
    'last_check' => date(DATE_RFC3339),
    'latency'    => max($results['full_check']->latency, $results['http_only']->latency),
    'type'       => strtolower(implode(',', array_unique(array_merge($results['full_check']->workingTypes, $results['http_only']->workingTypes)))),
    'status'     => 'active',
  ]);
} else {
  // mark as inactive
  $proxy_db->updateData($proxy, [
    'last_check' => date(DATE_RFC3339),
    'status'     => 'dead',
  ]);
}

// write worked proxy to file
writing_working_proxies_file($proxy_db, tmp('locks/working_proxies.txt'));
