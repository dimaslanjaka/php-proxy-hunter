<?php

// write ip ranges from CIDR into temp_folder/ips/

require_once __DIR__ . '/../func-proxy.php';

// disallow web server access
if (php_sapi_name() !== 'cli') {
  // Redirect the user away or show an error message
  header('HTTP/1.1 403 Forbidden');
  die('Direct access not allowed');
}

// set memory
ini_set('memory_limit', '1024M');

// CIDR source
$filePath = __DIR__ . '/CIDR.txt';

$outputDir = tmp() . '/ips';

// Read lines of the file into an array
$lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $cidr) {
  $output = $outputDir . '/' . sanitizeFilename($cidr) . '.txt';
  if (!file_exists($output)) {
    // write the ip ranges when file output not exist
    $ipList = getIPRange($cidr);
    write_file($output, implode(PHP_EOL, $ipList));
  }
}
