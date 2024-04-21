<?php

// scan ports from generated ip ranges ports

require_once __DIR__ . '/func.php';

// disallow web server access
if (php_sapi_name() !== 'cli') {
  // Redirect the user away or show an error message
  header('HTTP/1.1 403 Forbidden');
  die('Direct access not allowed');
}

// set memory
ini_set('memory_limit', '2024M');

$filePath = getRandomFileFromFolder(__DIR__ . '/tmp/ips-ports', 'txt');
$outputPath = __DIR__ . '/proxies.txt';
