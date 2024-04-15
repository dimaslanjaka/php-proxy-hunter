<?php

// check open port and move to test file

require_once __DIR__ . '/func.php';

// validate lock files
if (file_exists(__DIR__ . '/proxyChecker.lock') || file_exists(__DIR__ . '/proxySocksChecker.lock')) {
  exit('Another process still running');
}

// limit execution time seconds unit
$maxExecutionTime = 120;
$startTime = microtime(true);

$testPath = __DIR__ . '/proxies.txt';
$proxyPaths = [__DIR__ . '/proxies-all.txt', __DIR__ . '/dead.txt'];
shuffle($proxyPaths);
foreach ($proxyPaths as $file) {
  if (file_exists($file)) {
    $proxies = readFileLinesToArray($file);
    shuffle($proxies);
    foreach (array_unique(array_filter($proxies)) as $proxy) {
      if ((microtime(true) - $startTime) > $maxExecutionTime) {
        echo "maximum execution time excedeed ($maxExecutionTime)\n";
        // Execution time exceeded, break out of the loop
        return "break";
      }
      if (isPortOpen($proxy)) {
        echo trim($proxy) . PHP_EOL;
        removeStringAndMoveToFile(__DIR__ . '/proxies-all.txt', $testPath, trim($proxy));
      }
    }
  }
}


function isPortOpen($address)
{
  // Separate IP and port
  list($ip, $port) = explode(':', trim($address));

  // Create a TCP/IP socket
  $socket = @fsockopen($ip, $port, $errno, $errstr, 1);

  // Check if the socket could be opened
  if ($socket === false) {
    return false; // Port is closed
  } else {
    fclose($socket);
    return true; // Port is open
  }
}
