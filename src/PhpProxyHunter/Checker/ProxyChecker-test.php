<?php

require_once __DIR__ . '/../../../php_backend/shared.php';

global $proxy_db;

use PhpProxyHunter\Checker\ProxyCheckerPublicIP;
use PhpProxyHunter\Checker\ProxyCheckerHttpOnly;
use PhpProxyHunter\Checker\ProxyCheckerHttpsOnly;
use PhpProxyHunter\Checker\ProxyCheckerGoogle;
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
  'public_ip'  => ProxyCheckerPublicIP::check(new CheckerOptions($options)),
  'http_only'  => ProxyCheckerHttpOnly::check(new CheckerOptions($options)),
  'https_only' => ProxyCheckerHttpsOnly::check(new CheckerOptions($options)),
  'google'     => ProxyCheckerGoogle::check(new CheckerOptions($options)),
];

// Output neatly
foreach ($results as $type => $result) {
  echo strtoupper($type) . " RESULT:\n";
  var_dump($result);
  echo "\n";
}

// Save to database
// work when all result is working
// Consider proxy working if any checker reports success
if ($results['public_ip']->isWorking || $results['http_only']->isWorking || $results['https_only']->isWorking) {
  $formattedTypes = strtolower(implode('-', array_unique(array_merge($results['public_ip']->workingTypes, $results['http_only']->workingTypes, $results['https_only']->workingTypes, $results['google']->workingTypes))));
  echo 'Working types: ' . $formattedTypes . "\n";
  $proxy_db->updateData($proxy, [
    'https'      => $results['public_ip']->isSSL ? 'true' : 'false',
    'anonymity'  => $results['public_ip']->anonymity ?: $results['http_only']->anonymity,
    'last_check' => date(DATE_RFC3339),
    'latency'    => max($results['public_ip']->latency, $results['http_only']->latency, $results['https_only']->latency),
    'type'       => $formattedTypes,
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
writing_working_proxies_file($proxy_db, tmp('locks') . '/working_proxies.txt');
