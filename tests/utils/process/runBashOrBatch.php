<?php

require_once __DIR__ . '/../../../func.php';

$script            = __DIR__ . '/sample-error.php';
$cmd               = 'php ' . escapeshellarg($script);
$runner            = createBatchOrBashRunner('sample-error', $cmd);
$result            = runBashOrBatch($runner, [], 'sample-error', true);
$message           = json_decode($result['message'], true);
$result['message'] = $message;

// wait 5 seconds for the background process to complete
for ($i = 1; $i <= 5; $i++) {
  echo "waiting $i\n";
  sleep(1);
}

var_dump($result);

echo "\n=== LOG OUTPUT ===\n";
echo file_get_contents($message['output']);
echo "\n=== END LOG OUTPUT ===\n";
