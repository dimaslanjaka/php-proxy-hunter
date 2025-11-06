<?php

require_once __DIR__ . '/../../php_backend/shared.php';

global $proxy_db;

// Deny web access
if (php_sapi_name() !== 'cli') {
  http_response_code(403);
  exit('Forbidden');
}

$page    = 1;
$perPage = 1000;

while (true) {
  // log inline the page being processed
  echo "\rProcessing page $page";

  $proxies = $proxy_db->getAllProxies(null, null, $page, $perPage);
  if (empty($proxies)) {
    break;
  }
  foreach ($proxies as $data) {
    fixProxy($data);
  }
  // free memory between pages
  unset($proxies);
  gc_collect_cycles();
  $page++;
}

function fixProxy(array $data) {
  global $proxy_db;

  gc_collect_cycles(); // force garbage collection to free memory

  try {
    $normalized = $proxy_db->normalizeProxy($data['proxy']);
    if ($normalized === $data['proxy']) {
      // echo 'Skipping unchanged proxy: ' . $data['proxy'] . "\n";
      return;
    } else {
      echo "\nFixing proxy: " . $data['proxy'] . ' -> ' . $normalized . "\n";
      $clone          = $data;
      $clone['proxy'] = $normalized;
      $proxy_db->updateData($data['proxy'], $clone);
    }
  } catch (Exception $e) {
    echo 'Error normalizing proxy ' . $data['proxy'] . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    echo "----\n\n";
  }
}
