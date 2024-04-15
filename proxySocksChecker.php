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

$config = getConfig(getUserId());
$lockFilePath = __DIR__ . "/proxySocksChecker.lock";

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

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
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

// limit execution time seconds unit
$maxExecutionTime = 100;
$startTime = microtime(true);

$filePath = __DIR__ . "/socks.txt";
$workingPath = __DIR__ . "/socks-working.txt";
$deadPath = __DIR__ . "/socks-dead.txt";
$workingProxies = [];

rewriteIpPortFile($filePath);
rewriteIpPortFile($deadPath);
setFilePermissions([$filePath, $deadPath]);
shuffleChecks();

/**
 * run proxies check shuffled
 */
function shuffleChecks()
{
  global $startTime, $maxExecutionTime, $filePath, $workingPath, $workingProxies, $deadPath, $isCli;

  // Read lines of the file into an array
  $lines = file($filePath, FILE_IGNORE_NEW_LINES);
  if (empty(array_filter($lines))) {
    if (file_exists($deadPath)) {
      echo "proxies empty, respawning dead proxies\n\n";
      // respawn dead proxies
      rename($deadPath, $filePath);
      // repeat
      return shuffleChecks();
    } else {
      echo "no proxies to respawn";
      exit;
    }
  }

  // Shuffle the array
  shuffle($lines);

  // Iterate through the shuffled lines
  foreach ($lines as $line) {
    // Check if the elapsed time exceeds the limit
    if (((microtime(true) - $startTime) > $maxExecutionTime)) {
      echo "maximum execution time excedeed ($maxExecutionTime)\n";
      // Execution time exceeded, break out of the loop
      break;
    }
    $check5 = parseCheckResult(checkProxy(trim($line), '5'));
    $check4 = parseCheckResult(checkProxy(trim($line), '4'));
    if (!$check5['result'] && !$check4['result']) {
      // delete when both protocol dead
      removeStringAndMoveToFile($filePath, $deadPath, $check5['proxy']);
    }
  }

  // rewrite all working proxies
  if (count($workingProxies) > 1) file_put_contents($workingPath, join("\n", $workingProxies));
}

function parseCheckResult($check)
{
  global $workingPath, $workingProxies, $deadPath, $isCli;
  $type = "CURLPROXY_SOCKS" . $check['version'];
  echo trim($check['proxy']) . " $type " . ($check['result'] ? "working" : "dead") . " latency " . $check['latency'] . " ms" . PHP_EOL;
  if ($check['result']) {
    $proxy = trim($check['proxy']);
    $latency = $check['latency'];
    $item = "$proxy|$latency|$type";
    if (!in_array($item, $workingProxies)) {
      // If the item doesn't exist, push it into the array
      $workingProxies[] = $item;
    }
    // write working proxy
    file_put_contents($workingPath, join("\n", $workingProxies));
  }
  if (!$isCli && ob_get_level() > 0) {
    // LIVE output buffering on web server
    flush();
    ob_flush();
  }
  return $check;
}

/**
 * Checks the accessibility and latency of a proxy server.
 *
 * @param string $proxy The proxy address in the format IP:Port.
 * @param string $version The version of the SOCKS proxy protocol (4 or 5).
 * @return array An associative array containing the result and latency of the proxy check.
 */
function checkProxy(string $proxy, string $version = '5')
{
  $start = microtime(true);

  // Splitting the proxy address into IP and port
  // list($ip, $port) = explode(':', $proxy);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1254560890123456"); // Change URL to the one you want to test
  curl_setopt($ch, CURLOPT_PROXY, $proxy);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout in seconds
  curl_setopt($ch, CURLOPT_PROXYTYPE, $version == 5 ? CURLPROXY_SOCKS5 : CURLPROXY_SOCKS4); // Change to CURLPROXY_SOCKS4 if needed

  // Execute the request
  $output = curl_exec($ch);
  $totalTime = microtime(true) - $start;
  $latency = 0;
  // $output === false is error
  $result = $output !== false;
  if ($result) $latency = round($totalTime * 1000, 2);

  // Close cURL resource
  curl_close($ch);

  return ["result" => $result, "latency" => $latency, "proxy" => $proxy, "version" => $version];
}
