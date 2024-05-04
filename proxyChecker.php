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

require_once __DIR__ . "/func-proxy.php";

use PhpProxyHunter\ProxyDB;

$db = new ProxyDB();

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  if (function_exists('header')) {
    // Allow from any origin
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: *");
    header("Access-Control-Allow-Methods: *");
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Powered-By: L3n4r0x');
  }
  // only allow post
  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    exit('direct method disallowed');
  }
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
}

$config = getConfig(getUserId());
$endpoint = trim($config['endpoint']);
$headers = array_filter($config['headers']);
$checksFor = $config['type'];

echo $config['user_id'] . ' ' . date("Y-m-d H:i:s") . PHP_EOL;
echo "GET $endpoint " . strtoupper($checksFor) . PHP_EOL;
echo implode("\n", $headers) . PHP_EOL;

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

$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath)) {
  echo "another process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, $config['user_id'] . '=' . json_encode($config));
  file_put_contents($statusFile, 'running');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath)) unlink($lockFilePath);
  file_put_contents($statusFile, 'idle');
}
register_shutdown_function('exitProcess');

// Specify the file path
$filePath = __DIR__ . "/proxies.txt";
$socksPath = __DIR__ . "/socks.txt";
$socksWorkingPath = __DIR__ . '/socks-working.txt';
$workingPath = __DIR__ . "/working.txt";
$deadPath = __DIR__ . "/dead.txt";
$workingProxies = [];
$socksWorkingProxies = [];

setFilePermissions([$filePath, $workingPath, $deadPath]);

/**
 * run proxies check shuffled
 */
function shuffleChecks()
{
  global $filePath, $workingPath, $workingProxies, $deadPath, $isCli, $db;

  // Read lines of the file into an array
  $untested = extractProxies(file_get_contents($filePath));
  $working = extractProxies(file_get_contents($workingPath));
  $lines = array_merge($untested, $working);
  $lines = array_map(function ($line) {
    return trim($line->proxy);
  }, $working);
  $lines = array_unique($lines);
  shuffle($lines);

  // rewrite untested proxies
  file_put_contents($filePath, join("\n", $lines));

  if (empty($lines) || count($lines) < 30) {
    if (file_exists($deadPath)) {
      echo "proxies low, respawning dead proxies\n\n";
      // respawn 100 dead proxies
      moveLinesToFile($deadPath, $filePath, 100);
      // repeat
      return shuffleChecks();
    } else {
      echo "no proxies to respawn";
      exit();
    }
  }

  // Shuffle the array
  shuffle($lines);

  // Iterate through the shuffled lines
  foreach (array_unique($lines) as $line) {
    if (!$isCli && ob_get_level() > 0) {
      // LIVE output buffering on web server
      flush();
      ob_flush();
    }
    $from_db = $db->select($line);
    if (!empty($from_db)) {
      $dbproxy = $from_db[0];
      if (!is_null($dbproxy) && isset($dbproxy['last_check']) && !is_null($dbproxy['last_check']) && !isDateRFC3339OlderThanHours($dbproxy['last_check'], 5)) {
        // skip proxy already checked less than 5 hours ago
        echo "$line already checked" . PHP_EOL;
        continue;
      }
    }
    if (checkProxyLine($line) == "break") break;
    // move to dead.txt checked proxy
    removeStringAndMoveToFile($filePath, $deadPath, trim($line));
  }

  // rewrite all working proxies
  if (count($workingProxies) > 1) {
    file_put_contents($workingPath, join("\n", $workingProxies));
  }
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
  global $startTime, $maxExecutionTime, $workingProxies, $checksFor, $socksWorkingProxies, $db, $filePath, $deadPath, $endpoint, $headers;
  // Check if the elapsed time exceeds the limit
  if ((microtime(true) - $startTime) > $maxExecutionTime) {
    echo "maximum execution time excedeed ($maxExecutionTime)\n";
    // Execution time exceeded, break out of the loop
    return "break";
  }
  $proxy = trim($line);
  list($ip, $port) = explode(':', $proxy);
  $geoUrl = "http://ip-get-geolocation.com/api/json/$ip";

  if (!isPortOpen($proxy)) {
    echo "$proxy port closed\n";
    $db->updateStatus($proxy, 'port-closed');
    return "failed";
  }

  $successType = [];

  if (strpos($checksFor, 'http') !== false) {
    $check = checkProxy($proxy, 'http', $endpoint, $headers);
    if ($check['result'] !== false) {
      echo "$proxy working type HTTP";
      $latency = $check['latency'];
      if ($check['private']) {
        $db->update($proxy, 'http', null, null, null, 'private', $latency);
      } else {
        $db->update($proxy, 'http', null, null, null, 'active', $latency);
      }
      echo " latency $latency ms\n";
      $item = "$proxy|$latency|HTTP";
      // fetch ip info
      $geoIp = json_decode(curlGetWithProxy($geoUrl, $proxy, 'http'), true);
      // Check if JSON decoding was successful
      if ($geoIp !== null && json_last_error() === JSON_ERROR_NONE) {
        if (trim($geoIp['status']) != 'fail') {
          $item .= "|" . implode("|", [$geoIp['region'], $geoIp['city'], $geoIp['country'], $geoIp['timezone']]);
          $db->update($proxy, null, $geoIp['region'], $geoIp['city'], $geoIp['country'], null, null, $geoIp['timezone']);
        } else {
          $cachefile = curlGetCache($geoUrl);
          if (file_exists($cachefile)) unlink($cachefile);
        }
      }
      if (!in_array($item, $workingProxies)) {
        // If the item doesn't exist, push it into the array
        $workingProxies[] = $item;
      }
      // return "success";
      $successType[] = 'http';
    }
  }

  if (strpos($checksFor, 'socks5') !== false) {
    $check = checkProxy($proxy, 'socks5', $endpoint, $headers);
    if ($check['result'] !== false) {
      echo "$proxy working type SOCKS5\n";
      $latency = $check['latency'];
      if ($check['private']) {
        $db->update($proxy, 'socks5', null, null, null, 'private', $latency);
      } else {
        $db->update($proxy, 'socks5', null, null, null, 'active', $latency);
      }
      $item = "$proxy|$latency|SOCKS5";
      // fetch ip info
      $geoIp = json_decode(curlGetWithProxy($geoUrl, $proxy, 'socks5'), true);
      // Check if JSON decoding was successful
      if ($geoIp !== null && json_last_error() === JSON_ERROR_NONE) {
        if (trim($geoIp['status']) != 'fail') {
          $item .= "|" . implode("|", [$geoIp['region'], $geoIp['city'], $geoIp['country'], $geoIp['timezone']]);
          $db->update($proxy, null, $geoIp['region'], $geoIp['city'], $geoIp['country'], null, null, $geoIp['timezone']);
        } else {
          $cachefile = curlGetCache($geoUrl);
          if (file_exists($cachefile)) unlink($cachefile);
        }
      }
      if (!in_array($item, $socksWorkingProxies)) {
        // If the item doesn't exist, push it into the array
        $socksWorkingProxies[] = $item;
      }
      // return "success";
      $successType[] = 'socks5';
    }
  }

  if (strpos($checksFor, 'socks4') !== false) {
    $check = checkProxy($proxy, 'socks4', $endpoint, $headers);
    if ($check['result'] !== false) {
      echo "$proxy working type SOCKS4\n";
      $latency = $check['latency'];
      if ($check['private']) {
        $db->update($proxy, 'socks4', null, null, null, 'private', $latency);
      } else {
        $db->update($proxy, 'socks4', null, null, null, 'active', $latency);
      }
      $item = "$proxy|$latency|SOCKS4";
      // fetch ip info
      $geoIp = json_decode(curlGetWithProxy($geoUrl, $proxy, 'socks4'), true);
      // Check if JSON decoding was successful
      if ($geoIp !== null && json_last_error() === JSON_ERROR_NONE) {
        if (trim($geoIp['status']) != 'fail') {
          $item .= "|" . implode("|", [$geoIp['region'], $geoIp['city'], $geoIp['country'], $geoIp['timezone']]);
          $db->update($proxy, null, $geoIp['region'], $geoIp['city'], $geoIp['country'], null, null, $geoIp['timezone']);
        } else {
          $cachefile = curlGetCache($geoUrl);
          if (file_exists($cachefile)) unlink($cachefile);
        }
      }
      if (!in_array($item, $socksWorkingProxies)) {
        // If the item doesn't exist, push it into the array
        $socksWorkingProxies[] = $item;
      }
      // return "success";
      $successType[] = 'socks4';
    }
  }

  if (!empty($successType)) {
    $db->update($proxy, implode('-', $successType));
    return "success";
  }

  echo "$proxy not working\n";
  // remove dead proxy from check list
  $db->update($proxy, null, null, null, null, 'dead');
  // removeStringAndMoveToFile($filePath, $deadPath, trim($proxy));
  return "failed";
}

/// FUNCTIONS ENDS

// main script

function main()
{
  global $filePath, $deadPath;
  // move backup added proxies
  $backup = __DIR__ . '/proxies-backup.txt';
  if (file_exists($backup)) {
    if (moveContent($backup, $filePath)) {
      unlink($backup);
    }
  }
  // filter only IP:PORT each lines
  rewriteIpPortFile($filePath);
  rewriteIpPortFile($deadPath);
  // remove duplicate proxies
  removeDuplicateLines($filePath);
  removeDuplicateLines($deadPath);
  shuffleChecks();
  // sequentalChecks();
}

main();
