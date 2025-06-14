<?php

require __DIR__ . '/../func-proxy.php';

// Set time limit to 30 seconds
set_time_limit(30);

// Initialize the helper
$timer = new \PhpProxyHunter\ExecutionTimer(30, 3); // 30s limit, 3s safety buffer

while (true) {
  // Simulate processing
  usleep(500000); // 0.5s

  echo "Processing... Elapsed: " . round($timer->getElapsedTime(), 2) . "s" . PHP_EOL;

  // Check if we should exit early
  $timer->exitIfNeeded("Graceful exit: ran out of safe time.");
}
