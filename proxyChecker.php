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

require_once __DIR__ . "/proxyCheckerParallel-func.php";

use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Proxy;
use PhpProxyHunter\Scheduler;

global $isCli;

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
if (function_exists('set_time_limit')) {
  // Set the PHP maximum execution time to 5 minutes (300 seconds)
  set_time_limit(300);
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

$db = new ProxyDB(__DIR__ . '/src/database.sqlite');
$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";
$max_checks = 50;
/**
 * Array of strings to remove.
 *
 * @var string[]
 */
$str_to_remove = [];
$isAdmin = false;

if ($isCli) {
  $short_opts = "p:m::";
  $long_opts = [
    "proxy:",
    "max::",
    "userId::",
    "lockFile::",
    "runner::",
    "admin::"
  ];
  $options = getopt($short_opts, $long_opts);
  if (!empty($options['max'])) {
    $max = intval($options['max']);
    if ($max > 0) {
      $max_checks = $max;
    }
  }
  if (!empty($options['admin']) && $options['admin'] !== 'false') {
    $isAdmin = true;
    // set time limit 30 minutes for admin
    $maxExecutionTime = 30 * 60;
    // disable execution limit
    set_time_limit(0);
  }
}

if (file_exists($lockFilePath) && !is_debug() && !$isAdmin) {
  echo date(DATE_RFC3339) . ' another process still running' . PHP_EOL;
  exit();
} else {
  file_put_contents($lockFilePath, $config['user_id'] . '=' . json_encode($config));
  file_put_contents($statusFile, 'running');
}

Scheduler::register(function () use ($lockFilePath, $statusFile, $db) {
  // clean proxies.txt
  // clean_proxies_file(__DIR__ . '/proxies.txt');
  $data = parse_working_proxies($db);
  $countsString = implode("\n", array_map(function ($key, $value) {
    return "$key proxies $value";
  }, array_keys($data['counter']), $data['counter']));
  echo $countsString . PHP_EOL;
  echo "releasing lock" . PHP_EOL;
  // clean lock files
  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
  echo "update status to IDLE" . PHP_EOL;
  file_put_contents($statusFile, 'idle');
}, 'z_onExit' . basename(__FILE__));

// print cURL informations
echo "User " . $config['user_id'] . ' at ' . date("Y-m-d H:i:s") . PHP_EOL;
echo "GET $endpoint " . strtoupper($checksFor) . PHP_EOL;
echo implode("\n", $headers) . PHP_EOL;

// Specify the file path
$untestedFilePath = __DIR__ . "/proxies.txt";
$deadPath = __DIR__ . "/dead.txt";
$workingPath = __DIR__ . "/working.txt";
setMultiPermissions([$untestedFilePath, $workingPath, $deadPath], true);

// move backup added proxies
$countLinesUntestedProxies = countNonEmptyLines($untestedFilePath);
if ($countLinesUntestedProxies < 100 && file_exists($untestedFilePath) && filesize($untestedFilePath) === 0) {
  $assets = getFilesByExtension(__DIR__ . '/assets/proxies', 'txt');
  foreach ($assets as $asset) {
    if (strpos($asset, 'added-') !== false) {
      echo $asset . ' - ' . (file_exists($asset) ? 'true' : 'false') . PHP_EOL;
      if (file_exists($asset)) {
        $move = moveContent($asset, $untestedFilePath);
        if ($move === '') {
          unlink($asset);
          exit('copied assets' . PHP_EOL);
        } else {
          echo $move . PHP_EOL;
        }
      }
    }
  }
}

$untested = [];

try {
  $db_untested = $db->getUntestedProxies($max_checks);
  $db_working = $db->getWorkingProxies($max_checks);
  // include dead proxies when current date (minute unit) can be divided by 3
  // or if untested proxies less than $max_checks items
  $include_dead_proxies = date('i') % 3 == 0 || count($db_untested) < $max_checks || empty($db_untested);
  $db_dead = $include_dead_proxies ? $db->getDeadProxies($max_checks) : [];
  $db_data = array_merge($db_untested, $db_working, $db_dead);
  $db_data_map = array_map(function ($item) {
    $wrap = new Proxy($item['proxy']);
    foreach ($item as $key => $value) {
      if (property_exists($wrap, $key)) {
        $wrap->$key = $value;
      }
    }
    if (!empty($item['username']) && !empty($item['password'])) {
      $wrap->username = $item['username'];
      $wrap->password = $item['password'];
    }
    return $wrap;
  }, $db_data);
  $db_data_map = filter_proxies($db_data_map, $include_dead_proxies);
  $untested = array_merge($untested, $db_data_map);
  echo "[DB] queue: " . count($db_data_map) . " proxies" . PHP_EOL;
} catch (\Throwable $th) {
  echo "failed add untested proxies from database " . $th->getMessage() . PHP_EOL;
}

// get proxy from proxies.txt
if ($countLinesUntestedProxies > 0) {
  $file_untested_str = read_first_lines($untestedFilePath, 150);
  if (empty($file_untested_str)) {
    $file_untested_str = [];
  }
  $file_untested = extractProxies(implode("\n", $file_untested_str));
  $file_untested = filter_proxies($file_untested, date('i') % 3 == 0);
  echo "[FILE] queue: " . count($file_untested) . " proxies" . PHP_EOL;

  $untested = array_merge($untested, $file_untested);
  shuffle($untested);
}

if (empty($untested)) {
  // re-check dead proxies when both data (db & file) is empty
  $db_dead = $db->getDeadProxies(10000);
  $db_data_map = array_map(function ($item) {
    // check if dead proxy checked less than 1 hour ago
    if (!empty($item['last_check'])) {
      if (!isDateRFC3339OlderThanHours($item['last_check'], 24)) {
        // drop dead proxy when last checked less than 24 hour ago
        return null;
      }
    }
    // process transform to Proxy class
    $wrap = new Proxy($item['proxy']);
    foreach ($item as $key => $value) {
      if (property_exists($wrap, $key)) {
        $wrap->$key = $value;
      }
    }
    if (!empty($item['username']) && !empty($item['password'])) {
      $wrap->username = $item['username'];
      $wrap->password = $item['password'];
    }
    return $wrap;
  }, $db_dead);
  // assign untested
  $untested = array_filter(array_merge($untested, $db_data_map));
}

echo PHP_EOL;

execute_array_proxies();

function execute_array_proxies()
{
  global $untested, $max_checks;
  // filter proxies and skip dead proxies when current minute can be divided by 3
  $proxies = filter_proxies($untested, date('i') % 3 == 0);
  // skip empty array
  if (empty($proxies)) {
    return;
  }
  // shuffle array
  shuffle($proxies);

  iterateArray($proxies, $max_checks, 'execute_single_proxy');
}

/**
 * filter empty proxy, last checked only yesterday or empty
 * @param Proxy[] $proxies
 * @param bool $skip_dead_proxies
 * @return Proxy[]
 */
function filter_proxies(array $proxies, bool $skip_dead_proxies = false)
{
  global $db, $str_to_remove;
  if (empty($proxies)) {
    return [];
  }
  // unique proxies
  $proxies = uniqueClassObjectsByProperty($proxies, 'proxy');
  // skip already checked proxy
  $proxies = array_filter($proxies, function (Proxy $item) use ($db, $skip_dead_proxies) {
    $sel = $db->select($item->proxy);
    if (empty($sel)) {
      echo "add $item->proxy" . PHP_EOL;
      // add proxy
      $db->add($item->proxy, true);
      // re-select proxy
      $sel = $db->select($item->proxy);
    }
    if (!empty($sel) && empty($sel[0]['status'])) {
      echo "[SQLite] untested $item->proxy" . PHP_EOL;
      $db->updateStatus($item->proxy, 'untested');
    }
    $str_to_remove[] = $item->proxy;
    schedule_remover();
    if (empty($item->last_check) || empty($item->status)) {
      return true;
    }
    if ($skip_dead_proxies) {
      if ($item->status == 'dead' || $item->status == 'port-closed') {
        return false;
      }
    }
    return isDateRFC3339OlderThanHours($item->last_check, 24);
  });
  return $proxies;
}

function execute_single_proxy(Proxy $item)
{
  global $db, $headers, $endpoint, $startTime, $maxExecutionTime, $str_to_remove;
  // Check if execution time has exceeded the maximum allowed time
  $elapsedTime = microtime(true) - $startTime;
  if ($elapsedTime > $maxExecutionTime) {
    // Execution time exceeded
    echo "Execution time exceeded maximum allowed time of {$maxExecutionTime} seconds." . PHP_EOL;
    exit(0);
    return;
  }
  $raw_proxy = '';
  $proxyValid = isValidProxy($item->proxy);
  if ($proxyValid) {
    $raw_proxy = $item->proxy;
    if (!is_null($item->username) && !is_null($item->password)) {
      $raw_proxy .= '@' . $item->username . ':' . $item->password;
    }

    if (!isPortOpen($item->proxy)) {
      $db->updateStatus($item->proxy, 'port-closed');
      echo $item->proxy . ' port closed' . PHP_EOL;
    } else {
      $proxy_types = [];
      $anonymities = [];
      $latencies = [];
      $errors = [];

      $checks = [
        'http' => checkProxy($item->proxy, 'http', $endpoint, $headers, $item->username, $item->password),
        'socks5' => checkProxy($item->proxy, 'socks5', $endpoint, $headers, $item->username, $item->password),
        'socks4' => checkProxy($item->proxy, 'socks4', $endpoint, $headers, $item->username, $item->password)
      ];

      foreach ($checks as $type => $check) {
        if ($check['result']) {
          $proxy_types[] = $type;
        } else {
          // echo "$type://{$item->proxy} error: {$check['error']}" . PHP_EOL;
          $errors[] = "$type {$check['error']}";
        }
        if (!empty($check['anonymity'])) {
          $anonymities[] = $check['anonymity'];
        }
        $latencies[] = !empty($check['latency']) ? $check['latency'] : -1;
      }

      if (!empty($proxy_types)) {
        $merged_proxy_types = implode('-', $proxy_types);
        echo $item->proxy . ' working ' . strtoupper($merged_proxy_types) . ' latency ' . max($latencies) . ' ms' . PHP_EOL;
        $db->updateData($item->proxy, [
          'type' => $merged_proxy_types,
          'status' => 'active',
          'latency' => max($latencies),
          'username' => $item->username,
          'password' => $item->password,
          'https' => strpos($endpoint, 'https') !== false ? 'true' : 'false',
          'anonymity' => implode('-', array_unique($anonymities))
        ]);

        if (empty($item->webgl_renderer) || empty($item->browser_vendor) || empty($item->webgl_vendor)) {
          $webgl = random_webgl_data();
          $db->updateData($item->proxy, [
            'webgl_renderer' => $webgl->webgl_renderer,
            'webgl_vendor' => $webgl->webgl_vendor,
            'browser_vendor' => $webgl->browser_vendor
          ]);
        }

        if (empty($item->timezone) || empty($item->country) || empty($item->lang)) {
          foreach ($proxy_types as $type) {
            get_geo_ip($item->proxy, $type, $db);
          }
        }

        if (empty($item->useragent) && strlen(trim($item->useragent)) <= 5) {
          $item->useragent = randomWindowsUa();
          $db->updateData($item->proxy, ['useragent' => $item->useragent]);
        }
      } else {
        $error = join(" ", $errors);
        if (strpos($error, 'anonymity') !== false) {
          $db->updateStatus($item->proxy, 'untested');
          echo $item->proxy . ' failed obtain anoymity' . PHP_EOL;
        } else {
          $db->updateStatus($item->proxy, 'dead');
          echo $item->proxy . ' dead' . PHP_EOL;
        }
      }
    }
  } else {
    $raw_proxy = $item->proxy;
    try {
      $db->remove($item->proxy);
      echo $item->proxy . ' invalid proxy -> deleted' . PHP_EOL;
    } catch (Exception $e) {
      $errorMessage = $e->getMessage();
      // Handle or display the error message
      echo "fail delete " . $item->proxy . ' : ' . $errorMessage . PHP_EOL;
    }
  }
  // add to remover scheduler
  if (!empty($raw_proxy)) {
    $str_to_remove[] = $raw_proxy;
  }
  schedule_remover();
}

function schedule_remover()
{
  global $str_to_remove;
  if (!empty($str_to_remove)) {
    // remove already indexed proxies
    \PhpProxyHunter\Scheduler::register(function () use ($str_to_remove) {
      $files = [__DIR__ . '/dead.txt', __DIR__ . '/proxies.txt', __DIR__ . '/proxies-all.txt'];
      $assets = array_filter(getFilesByExtension(__DIR__ . '/assets/proxies'), function ($fn) {
        return strpos($fn, 'added-') !== false;
      });
      $files = array_merge($files, $assets);
      $files = array_filter($files, 'file_exists');
      $files = array_map('realpath', $files);
      foreach ($files as $file) {
        $remove = removeStringFromFile($file, $str_to_remove);
        if ($remove == 'success') {
          echo "removed indexed proxies from " . basename($file) . PHP_EOL;
          sleep(1);
          removeEmptyLinesFromFile($file);
        }
        sleep(1);
        if (filterIpPortLines($file) == 'success') {
          echo "non IP:PORT lines removed from " . basename($file) . PHP_EOL;
        }
        sleep(1);
      }
    }, "remove indexed proxies");
  }
}
