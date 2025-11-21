<?php

require_once __DIR__ . '/../../php_backend/shared.php';

$refresh  = refreshDbConnections();
$core_db  = $refresh['core_db'];
$user_db  = $refresh['user_db'];
$proxy_db = $refresh['proxy_db'];
$log_db   = $refresh['log_db'];

$sslProxies    = [];
$nonSslProxies = [];

$MAX_TOTAL = 1000;
// <-- HARD LIMIT

for ($i = 0; $i < 3; $i++) {
  // Fetch 100 working proxies per page
  $holder = $proxy_db->getWorkingProxies(null, true, $i + 1, 100);

  if (empty($holder)) {
    break;
    // no more proxies available
  }

  foreach ($holder as $data) {
    // Check if we've reached the max before adding anything
    if (count($sslProxies) + count($nonSslProxies) >= $MAX_TOTAL) {
      break 2;
      // stop both foreach and for loops
    }

    // Display proxy info
    echo $data['proxy'] . "\n";
    echo '  SSL: ' . ($data['https'] === 'true' ? 'Yes' : 'No') . "\n";
    echo '  Type: ' . $data['type'] . "\n";
    echo '  Last Checked: ' . ($data['last_check'] ? timeAgo($data['last_check'], true) : 'N/A') . "\n";

    // Add proxy to SSL or non-SSL bucket
    if ($data['https'] === 'true') {
      $sslProxies[] = $data;
    } else {
      $nonSslProxies[] = $data;
    }
  }

  // release memory per page
  unset($holder);
  gc_collect_cycles();
}

// final cleanup
gc_collect_cycles();

echo "\n=== Summary ===\n";
echo 'SSL proxies:     ' . count($sslProxies) . "\n";
echo 'Non-SSL proxies: ' . count($nonSslProxies) . "\n";
echo 'Total:           ' . (count($sslProxies) + count($nonSslProxies)) . "\n";
