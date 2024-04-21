<?php

// write ip ranges from CIDR

require_once __DIR__ . '/func.php';

// CIDR source
$filePath = __DIR__ . "/CIDR.txt";

$outputDir = __DIR__ . '/tmp/ips';
if (!file_exists($outputDir)) mkdir($outputDir, 0777, true);

// Read lines of the file into an array
$lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $cidr) {
  $output = $outputDir . '/' . sanitizeFilename($cidr) . '.txt';
  if (!file_exists($output)) {
    // write the ip ranges when file output not exist
    $ipList = getIPRange($cidr);
    file_put_contents($output, implode(PHP_EOL, $ipList));
  }
}
