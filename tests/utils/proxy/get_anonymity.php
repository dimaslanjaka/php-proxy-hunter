<?php

require_once __DIR__ . '/../../../php_backend/shared.php';

$refresh  = refreshDbConnections();
$core_db  = $refresh['core_db'];
$user_db  = $refresh['user_db'];
$proxy_db = $refresh['proxy_db'];
$log_db   = $refresh['log_db'];

$proxy            = $proxy_db->getWorkingProxies(1, true)[0]['proxy'];
$protocols        = ['http', 'socks4a', 'socks5h', 'socks4', 'socks5'];
$workingProtocols = [];
foreach ($protocols as $protocol) {
  echo "Checking proxy connection for $protocol://$proxy...\n";
  $connection = checkProxy($proxy, $protocol);
  $isWorking  = $connection['result'] === true;
  if ($isWorking) {
    echo 'Anonymity: ' . ($connection['anonymity'] ?? 'unknown') . "\n";
    echo 'Latency (ms): ' . ($connection['latency'] ?? 'N/A') . "\n";
  } else {
    echo "Proxy not working for $protocol://$proxy.\n";
  }
  // get anonymity level
  echo "Checking anonymity level for $protocol://$proxy...\n";
  $anonymity = get_anonymity($proxy, $protocol);
  if (!empty($anonymity)) {
    echo "Anonymity level for $protocol://$proxy: $anonymity\n";
  } else {
    echo "Could not determine anonymity level for $protocol://$proxy.\n";
  }
  // assign working protocols
  if (!empty($anonymity) && $isWorking) {
    $workingProtocols[] = $protocol;
  }
  echo "----------------------------------------\n";
}

if (!empty($workingProtocols)) {
  echo "Proxy $proxy is working for protocols: " . implode(', ', $workingProtocols) . "\n";
} else {
  echo "Proxy $proxy is not working for any tested protocols.\n";
  $proxy_db->updateStatus($proxy, 'dead');
}
