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
use PhpProxyHunter\Proxy;

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

echo "User " . $config['user_id'] . ' at ' . date("Y-m-d H:i:s") . PHP_EOL;
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

/// FUNCTIONS (DO NOT EDIT)

$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath) && gethostname() !== 'DESKTOP-JVTSJ6I') {
  echo "another process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, $config['user_id'] . '=' . json_encode($config));
  file_put_contents($statusFile, 'running');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath))
    unlink($lockFilePath);
  file_put_contents($statusFile, 'idle');

  try {
    // remove duplicate lines from proxies.txt
    removeDuplicateLines(__DIR__ . '/proxies.txt');
  } catch (Exception $e) {
    // Handle any exceptions that occur during the execution of removeDuplicateLines
    echo 'Error removing duplicate lines from proxies.txt: ' . $e->getMessage() . PHP_EOL;
  }

  try {
    // remove lines less than 10 size
    removeShortLines(__DIR__ . '/proxies.txt', 10);
  } catch (Exception $e) {
    // Handle any exceptions that occur during the execution of removeDuplicateLines
    echo 'Error removing short lines from proxies.txt: ' . $e->getMessage() . PHP_EOL;
  }

  try {
    // remove duplicate lines from dead.txt
    removeDuplicateLines(__DIR__ . '/dead.txt');
  } catch (Exception $e) {
    // Handle any exceptions that occur during the execution of removeDuplicateLines
    echo 'Error removing duplicate lines from dead.txt: ' . $e->getMessage() . PHP_EOL;
  }

  try {
    fixFile(__DIR__ . "/proxies.txt");
  } catch (\Throwable $th) {
    echo 'Error fix bad contents from proxies.txt: ' . $e->getMessage() . PHP_EOL;
  }

  try {
    // remove duplicate lines in untested proxies
    $file1 = realpath(__DIR__ . "/proxies.txt");
    $file2 = realpath(__DIR__ . "/dead.txt");

    if (removeDuplicateLinesFromSource($file1, $file2)) {
      echo "Duplicated lines between $file1 and $file2 removed successful from $file1" . PHP_EOL;
    }
  } catch (Exception $e) {
    // Handle any exceptions that occur during the execution of removeDuplicateLinesInUntestedProxies
    echo 'Error removing duplicate lines in untested proxies: ' . $e->getMessage() . PHP_EOL;
  }
}

register_shutdown_function('exitProcess');

// Specify the file path
$filePath = __DIR__ . "/proxies.txt";
$deadPath = __DIR__ . "/dead.txt";
$workingPath = __DIR__ . "/working.txt";
setFilePermissions([$filePath, $workingPath, $deadPath]);

// move backup added proxies
$backup = __DIR__ . '/proxies-backup.txt';
if (file_exists($backup)) {
  if (moveContent($backup, $filePath)) {
    unlink($backup);
  }
}

$max_checks = 50;
$db = new ProxyDB(__DIR__ . '/src/database.sqlite');

iterateBigFilesLineByLine([$filePath], $max_checks, function (string $line) {
  global $db, $max_checks, $filePath;
  if (strlen($line) < 10) {
    // invalid proxy string, remove from source
    removeStringFromFile($filePath, trim($line));
    return;
  }
  $untested = extractProxies($line);
  // add untested proxies from database
//  try {
//    $db_untested = $db->getUntestedProxies(1000);
//    $db_untested_map = array_map(function ($item) {
//      $wrap = new Proxy($item['proxy']);
//      foreach ($item as $key => $value) {
//        if (property_exists($wrap, $key)) {
//          $wrap->$key = $value;
//        }
//      }
//      if (!empty($item['username']) && !empty($item['password'])) {
//        $wrap->username = $item['username'];
//        $wrap->password = $item['password'];
//      }
//      return $wrap;
//    }, $db_untested);
//    $untested = array_merge($untested, $db_untested_map);
//  } catch (\Throwable $th) {
//    echo "failed add untested proxies from database " . $th->getMessage() . PHP_EOL;
//  }

//  if (count($untested) < $max_checks) {
//    $working = array_map(function ($item) {
//      $wrap = new Proxy($item['proxy']);
//      foreach ($item as $key => $value) {
//        if (property_exists($wrap, $key)) {
//          $wrap->$key = $value;
//        }
//      }
//      return $wrap;
//    }, $db->getWorkingProxies());
//    $proxies = array_merge($untested, $working);
//  } else {
  $proxies = $untested;
//  }
//
//  if (count($proxies) < $max_checks) {
//    if (file_exists($deadPath)) {
//      echo "proxies low, respawning dead proxies" . PHP_EOL;
//      // respawn 100 dead proxies
//      moveLinesToFile($deadPath, $filePath, 100);
//      exit;
//    }
//  }

  $proxies = uniqueClassObjectsByProperty($proxies, 'proxy');
  $proxies = array_filter($proxies, function (Proxy $item) {
    if (empty($item->last_check)) return true;
    return isDateRFC3339OlderThanHours($item->last_check, 5);
  });
  shuffle($proxies);

//  echo "total proxies to be tested " . count($proxies) . PHP_EOL . PHP_EOL;

  iterateArray($proxies, $max_checks, 'execute_single_proxy');
});

function execute_single_proxy(Proxy $item)
{
  global $db, $headers, $endpoint, $filePath, $deadPath, $startTime, $maxExecutionTime;
  // Check if execution time has exceeded the maximum allowed time
  $elapsedTime = microtime(true) - $startTime;
  if ($elapsedTime > $maxExecutionTime) {
    // Execution time exceeded
    //    echo "Execution time exceeded maximum allowed time of {$maxExecutionTime} seconds." . PHP_EOL;
    return;
  }
  //  get_geo_ip($item->proxy); // just test
  $proxyValid = false;
  $proxyLength = strlen($item->proxy);
  if ($proxyLength > 10 && $proxyLength <= 21) {
    list($ip, $port) = explode(":", $item->proxy, 2);
    $ipValid = strlen($ip) >= 7 && strlen($ip) <= 15 && filter_var($ip, FILTER_VALIDATE_IP);
    $portValid = filter_var($port, FILTER_VALIDATE_INT, array(
        "options" => array(
            "min_range" => 1,
            "max_range" => 65535
        )
    ));
    if ($ipValid && $portValid) {
      $proxyValid = true;
      $raw_proxy = $item->proxy;
      if (!is_null($item->username) && !is_null($item->password)) $raw_proxy .= '@' . $item->username . ':' . $item->password;

      if (!isPortOpen($item->proxy)) {
        $db->updateStatus($item->proxy, 'port-closed');
        echo $item->proxy . ' port closed' . PHP_EOL;
        // always move checked proxy into dead.txt
        removeStringAndMoveToFile($filePath, $deadPath, $raw_proxy);
        return;
      }

      $proxy_types = [];
      $check_http = checkProxy($item->proxy, 'http', $endpoint, $headers, $item->username, $item->password);
      $check_socks5 = checkProxy($item->proxy, 'socks5', $endpoint, $headers, $item->username, $item->password);
      $check_socks4 = checkProxy($item->proxy, 'socks4', $endpoint, $headers, $item->username, $item->password);
      if ($check_http['result']) $proxy_types[] = 'http';
      if ($check_socks5['result']) $proxy_types[] = 'socks5';
      if ($check_socks4['result']) $proxy_types[] = 'socks4';
      $latencies = [$check_http['latency'], $check_socks5['latency'], $check_socks4['latency']];
      if (!empty($proxy_types)) {
        // proxy working
        $merged_proxy_types = implode('-', $proxy_types);
        echo $item->proxy . ' working ' . strtoupper($merged_proxy_types) . ' latency ' . max($latencies) . ' ms' . PHP_EOL;
        $db->updateData($item->proxy, [
            'type' => $merged_proxy_types,
            'status' => 'active',
            'latency' => max($latencies),
            'username' => $item->username,
            'password' => $item->password
        ]);
        if (empty($item->webgl_renderer) || empty($item->browser_vendor) || empty($item->webgl_vendor)) {
          $webgl = random_webgl_data();
          $db->updateData($item->proxy, [
              'webgl_renderer' => $webgl->webgl_renderer,
              'webgl_vendor' => $webgl->webgl_vendor,
              'browser_vendor' => $webgl->browser_vendor
          ]);
        }
        // get geolocation
        if (empty($item->timezone) || empty($item->country) || empty($item->lang)) {
          if (in_array('http', $proxy_types)) get_geo_ip($item->proxy);
          if (in_array('socks5', $proxy_types)) get_geo_ip($item->proxy, 'socks5');
          if (in_array('socks4', $proxy_types)) get_geo_ip($item->proxy, 'socks4');
        }

        // update proxy useragent
        if (empty($item->useragent) && strlen(trim($item->useragent)) <= 5) {
          $item->useragent = randomWindowsUa();
          $db->updateData($item->proxy, ['useragent' => $item->useragent]);
        }
      } else {
        $db->updateStatus($item->proxy, 'dead');
        echo $item->proxy . ' dead' . PHP_EOL;
      }
      // always move checked proxy into dead.txt
      removeStringAndMoveToFile($filePath, $deadPath, $raw_proxy);
    }
  }
  if (!$proxyValid) {
    try {
      $db->remove($item->proxy);
      echo $item->proxy . ' invalid proxy -> deleted' . PHP_EOL;
    } catch (Exception $e) {
      $errorMessage = $e->getMessage();
      // Handle or display the error message
      echo "fail delete " . $item->proxy . ' : ' . $errorMessage . PHP_EOL;
    }
  }
}

// write working proxies to working.txt
//$workingProxies = $db->getWorkingProxies();
//$array_format = array_map(function ($item) {
//  foreach ($item as $key => $value) {
//    if (empty($value)) {
//      $item[$key] = '-';
//    }
//  }
//
//  unset($item['id']);
//  $item['type'] = strtoupper($item['type']);
//  return implode('|', $item);
//}, $workingProxies);
//$string_format = implode(PHP_EOL, $array_format);
//file_put_contents($workingPath, $string_format);
