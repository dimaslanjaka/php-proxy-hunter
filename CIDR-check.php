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
$proxies = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($proxies as $proxy) {
  $proxy = trim($proxy);
  if (isPortOpen($proxy)) {
    $http = checkProxy($proxy, 'http');
    $socks5 = checkProxy($proxy, 'socks5');
    $socks4 = checkProxy($proxy, 'socks4');
    if ($http || $socks4 || $socks5) {
      echo "$proxy working" . PHP_EOL;
      removeStringAndMoveToFile($filePath, $outputPath, $proxy);
    } else {
      removeStringFromFile($filePath, $proxy);
      echo "$proxy port open, but not proxy" . PHP_EOL;
    }
  } else {
    removeStringFromFile($filePath, $proxy);
    echo "$proxy port closed" . PHP_EOL;
  }
}
