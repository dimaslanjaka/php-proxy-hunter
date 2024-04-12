<?php

/*
  ----------------------------------------------------------------------------
  LICENSE
  ----------------------------------------------------------------------------
  This file is part of Proxy Checker.

  Proxy Checker is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Proxy Checker is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with Proxy Checker.  If not, see <https://www.gnu.org/licenses/>.

  ----------------------------------------------------------------------------
  Copyright (c) 2024 Dimas lanjaka
  ----------------------------------------------------------------------------
  This project is licensed under the GNU General Public License v3.0
  For full license details, please visit: https://www.gnu.org/licenses/gpl-3.0.html

  If you have any inquiries regarding the license or permissions, please contact:

  Name: Dimas Lanjaka
  Website: https://www.webmanajemen.com
  Email: dimaslanjaka@gmail.com
*/

require_once __DIR__ . "/func.php";

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
}

// limit execution time seconds unit
$maxExecutionTime = 120;
$startTime = microtime(true);
// ignore limitation if exists
if (function_exists('set_time_limit')) {
  if (gethostname() == "DESKTOP-JVTSJ6I") {
    call_user_func('set_time_limit', 0);
  } else {
    call_user_func('set_time_limit', $maxExecutionTime);
  }
}

// set output buffering to zero
// avoid error while running on CLI
if (!$isCli) {
  ini_set('output_buffering', 0);
  if (ob_get_level() == 0) {
    ob_start();
  }
  if (function_exists('header')) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Powered-By: L3n4r0x');
  }
}

$config = getConfig(getUserId());
$endpoint = trim($config['endpoint']);
$headers = $config['headers'];

echo "GET $endpoint\n";
echo implode("\n", $headers) . "\n";

if (!$isCli) {
  if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $origin = $protocol . $_SERVER['HTTP_HOST'];
  }
  if (isset($origin)) {
    echo "working proxies $origin/working.txt\n";
    echo "dead proxies $origin/dead.txt\n";
  }
}

echo "\n";

/// FUNCTIONS (DO NOT EDIT)

$script = md5(__FILE__);
$lockFilePath = __DIR__ . "/$script.lock";

if (isset($_REQUEST['isRunning'])) {
  echo "is running: " . (file_exists($lockFilePath) ? 'true' : 'false');
  exit();
}

if (file_exists($lockFilePath)) {
  echo "another process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, $config['user_id'] . '=' . json_encode($config));
}

function exitProcess()
{
  global $lockFilePath;
  if (file_exists($lockFilePath)) unlink($lockFilePath);
}
register_shutdown_function('exitProcess');

// Specify the file path
$filePath = __DIR__ . "/proxies.txt";
$workingPath = __DIR__ . "/working.txt";
$deadPath = __DIR__ . "/dead.txt";
$workingProxies = [];
$checksFor = "http"; // "socks";

setFilePermissions([$filePath, $workingPath, $deadPath]);

/**
 * run proxies check shuffled
 */
function shuffleChecks()
{
  global $filePath, $workingPath, $workingProxies, $deadPath;

  // Read lines of the file into an array
  $lines = file($filePath, FILE_IGNORE_NEW_LINES);
  if (empty(array_filter($lines))) {
    echo "proxies empty, respawning dead proxies\n\n";
    // respawn dead proxies
    rename($deadPath, $filePath);
    // repeat
    return shuffleChecks();
  }

  // Shuffle the array
  shuffle($lines);

  // Iterate through the shuffled lines
  foreach ($lines as $line) {
    if (checkProxyLine($line) == "break") break;
  }

  // rewrite all working proxies
  if (count($workingProxies) > 1) file_put_contents($workingPath, join("\n", $workingProxies));
}

function stripDeadProxy(string $proxy)
{
  global $filePath, $deadPath;

  $sourceFile = fopen($filePath, 'r') or die('Unable to open source file');
  $destinationFile = fopen($deadPath, 'a') or die('Unable to open destination file');
  $tempFile = fopen(__DIR__ . '/temp.txt', 'w') or die('Unable to open temporary file');

  // Loop through each line in the source file
  while (!feof($sourceFile)) {
    $line = fgets($sourceFile);

    // Check if the line matches the pattern
    if (trim($line) == $proxy) {
      // Write the matching line to the destination file on a new line
      fwrite($destinationFile, $line);
    } else {
      // Write non-matching lines to the temporary file
      fwrite($tempFile, $line);
    }
  }

  // Close the files
  fclose($sourceFile);
  fclose($destinationFile);
  fclose($tempFile);

  // Replace the source file with the temporary file
  rename(__DIR__ . '/temp.txt', $filePath);
}

/**
 * check single proxy and append into global working proxies
 * @return string break, success, failed
 * * break: the execution time excedeed
 * * success: proxy working
 * * failed: proxy not working
 */
function checkProxyLine($line)
{
  global $startTime, $maxExecutionTime, $workingPath, $workingProxies, $isCLI, $checksFor;
  // Check if the elapsed time exceeds the limit
  if (microtime(true) - $startTime > $maxExecutionTime && !$isCLI) {
    echo "maximum execution time excedeed ($maxExecutionTime)\n";
    // Execution time exceeded, break out of the loop
    return "break";
  }
  $proxy = trim($line);

  if (strpos($checksFor, 'http') !== false) {
    if (checkProxy($proxy)) {
      echo "$proxy working type CURLPROXY_HTTP";
      $latency = checkProxyLatency($proxy);
      echo " latency $latency ms\n";
      if (!$isCLI && ob_get_level() > 0) {
        // LIVE output buffering on web server
        flush();
        ob_flush();
      }
      $item = "$proxy|$latency|CURLPROXY_HTTP";
      if (!in_array($item, $workingProxies)) {
        // If the item doesn't exist, push it into the array
        $workingProxies[] = $item;
      }
      // write working proxy
      file_put_contents($workingPath, join("\n", $workingProxies));
      return "success";
    }
  }

  if (strpos($checksFor, 'socks') !== false) {
    if (checkSocksProxy($proxy)) {
      echo "$proxy working type CURLPROXY_SOCKS5\n";
      $latency = measureSocksProxyLatency($proxy);
      $item = "$proxy|$latency|CURLPROXY_SOCKS5";
      if (!in_array($item, $workingProxies)) {
        // If the item doesn't exist, push it into the array
        $workingProxies[] = $item;
      }
      file_put_contents($workingPath, join("\n", $workingProxies));
      return "success";
    }
  }

  echo "$proxy not working\n";
  stripDeadProxy($proxy);
  return "failed";
}

function extractIpPortFromFile($filePath)
{
  $ipPortList = array();

  // Open the file for reading
  $file = fopen($filePath, "r");

  // Read each line from the file
  while (!feof($file)) {
    $line = fgets($file);

    // Match IP:PORT pattern using regular expression
    preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+)/', $line, $matches);

    // Add matched IP:PORT combinations to the list
    foreach ($matches[0] as $match) {
      $ipPortList[] = $match;
    }
  }

  // Close the file
  fclose($file);

  return $ipPortList;
}

/**
 * Function to measure the latency of a SOCKS proxy by establishing a connection to a specified endpoint.
 *
 * @param string $proxy The SOCKS proxy in the format IP:PORT.
 * @param string $endpoint The URL of the endpoint to connect to for latency measurement.
 * @param int $timeout The timeout for the connection attempt, in seconds.
 * @return float|false The latency in seconds if the proxy is reachable, or false if unreachable.
 */
function measureSocksProxyLatency(string $proxy, $timeout = 5)
{
  global $endpoint;
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  curl_setopt($ch, CURLOPT_PROXY, $proxy);
  curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5); // Change to SOCKS4 if necessary
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  // Start timing
  $start_time = microtime(true);

  // Execute the request
  $result = curl_exec($ch);

  // Stop timing
  $end_time = microtime(true);

  // Check if there was an error
  if (curl_errno($ch)) {
    // Error occurred during curl execution
    curl_close($ch);
    return false;
  }

  // Calculate latency
  $latency = $end_time - $start_time;

  curl_close($ch);
  return $latency != false ? round($latency, 2) : false;
}

/**
 * Function to check if a SOCKS proxy is working by attempting to connect to a specified endpoint.
 *
 * @param string $proxy The SOCKS proxy in the format IP:PORT.
 * @return bool True if the proxy is working, false otherwise.
 */
function checkSocksProxy(string $proxy)
{
  global $endpoint, $headers;
  $timeout = 10; // Adjust timeout as needed

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  curl_setopt($ch, CURLOPT_PROXY, $proxy);
  curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5); // Change to SOCKS4 if necessary
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate'); // handles compressed response

  // Execute the request
  $result = curl_exec($ch);

  // Check if there was an error
  if (curl_errno($ch)) {
    // Error occurred during curl execution
    curl_close($ch);
    return false;
  }

  // Check HTTP status code
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ($http_code >= 200 && $http_code < 300);
}

/**
 * check http proxy latency
 */
function checkProxyLatency($proxy)
{
  global $endpoint;
  $start = microtime(true); // Start time

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint); // URL to test connectivity
  curl_setopt($ch, CURLOPT_PROXY, $proxy); // Proxy address
  curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); // Specify proxy type, adjust accordingly

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Set maximum connection time
  curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set maximum response time

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);

  $response = curl_exec($ch);
  $info = curl_getinfo($ch);

  curl_close($ch);

  $end = microtime(true); // End time

  if ($response === false || $info['http_code'] != 200) {
    return -1; // Proxy not working or unable to connect
  }

  $latency = round(($end - $start) * 1000); // Convert to milliseconds

  return $latency; // Latency in milliseconds
}

/**
 * check http proxy
 */
function checkProxy($proxy)
{
  global $endpoint, $headers;
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint); // URL to test connectivity
  curl_setopt($ch, CURLOPT_PROXY, $proxy); // Proxy address
  curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); // Specify proxy type, adjust accordingly

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Set maximum connection time
  curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set maximum response time

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate'); // handles compressed response

  $response = curl_exec($ch);
  $info = curl_getinfo($ch);

  curl_close($ch);

  if ($response === false || $info['http_code'] != 200) {
    return false; // Proxy not working or unable to connect
  }

  return true; // Proxy working
}

/**
 * remove duplicate lines from file
 */
function removeDuplicateLines($filePath)
{
  // Read the file into an array, each line as an element
  $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

  // Remove duplicate lines
  $lines = array_unique($lines);

  // Remove empty strings from the array
  $lines = array_filter($lines, function ($value) {
    return trim($value) !== '';
  });

  // Write the modified lines back to the file
  file_put_contents($filePath, implode("\n", $lines) . "\n");
}

/// FUNCTIONS ENDS

// main script

function main()
{
  global $filePath, $deadPath;
  // remove duplicate proxies
  rewriteIpPortFile($filePath);
  removeDuplicateLines($filePath);
  removeDuplicateLines($deadPath);
  shuffleChecks();
  // sequentalChecks();
}

main();
