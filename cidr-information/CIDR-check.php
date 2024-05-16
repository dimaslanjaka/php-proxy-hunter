<?php

// scan ports from generated ip ranges ports

require_once __DIR__ . '/../func.php';

// disallow web server access
if (php_sapi_name() !== 'cli') {
  // Redirect the user away or show an error message
  header('HTTP/1.1 403 Forbidden');
  die('Direct access not allowed');
}

$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath)) {
  echo "another process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'scan generated IP:PORT');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath)) unlink($lockFilePath);
  file_put_contents($statusFile, 'idle');
}
register_shutdown_function('exitProcess');

// set memory
ini_set('memory_limit', '2024M');

$filePath = getRandomFileFromFolder(__DIR__ . '/tmp/ips-ports', 'txt');
$outputPath = __DIR__ . '/proxies.txt';

if (!is_file($filePath)) {
  exit($filePath . ' is not file');
}

// Open the file for reading
$fileHandle = fopen($filePath, "r");

// Check if the file opened successfully
if ($fileHandle) {
  // Open the output file for appending
  $outputHandle = fopen($outputPath, "a"); // Changed mode to "a"

  // Check if the output file opened successfully
  if ($outputHandle) {
    $startTime = time(); // Get the current timestamp

    // Read the file line by line
    while (($proxy = fgets($fileHandle)) !== false) {
      // Check if the elapsed time is more than [n] seconds
      if (time() - $startTime > 300) {
        echo "Execution time exceeded. Stopping execution." . PHP_EOL;
        // Break out of the loop
        break;
      }

      $proxy = trim($proxy);

      // dont process invalid IP:PORT
      if (empty($proxy) || !isValidIPPort($proxy)) {
        // Remove the proxy from the input file
        removeStringFromFile($filePath, $proxy);
        continue;
      }

      if (isPortOpen($proxy)) {
        $http = checkProxy($proxy, 'http');
        $socks5 = checkProxy($proxy, 'socks5');
        $socks4 = checkProxy($proxy, 'socks4');
        if ($http || $socks4 || $socks5) {
          echo "$proxy working" . PHP_EOL;
          // Write the proxy to the output file
          fwrite($outputHandle, PHP_EOL . $proxy . PHP_EOL);
          // Remove the proxy from the input file
          removeStringFromFile($filePath, $proxy);
        } else {
          echo "$proxy port open, but not proxy" . PHP_EOL;
          // Remove the proxy from the input file
          removeStringFromFile($filePath, $proxy);
        }
      } else {
        echo "$proxy port closed" . PHP_EOL;
        // Remove the proxy from the input file
        removeStringFromFile($filePath, $proxy);
      }
    }

    // Close the output file
    fclose($outputHandle);
  } else {
    echo "Error: Unable to open output file!";
  }

  // Close the input file
  fclose($fileHandle);
} else {
  echo "Error: Unable to open input file!";
}

// filter IP:PORT only
rewriteIpPortFile($outputPath);
rewriteIpPortFile($filePath);
