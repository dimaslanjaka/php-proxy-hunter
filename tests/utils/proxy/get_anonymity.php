<?php

require_once __DIR__ . '/../../../func-proxy.php';

$proxy            = '91.238.105.64:2024';
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
  echo "----------------------------------------\n";
}
