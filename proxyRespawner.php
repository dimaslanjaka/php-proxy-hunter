<?php

/** @noinspection PhpVariableIsUsedOnlyInClosureInspection */

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

// check open port and move to test file

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

require_once __DIR__ . '/func-proxy.php';

// validate lock files
$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";
$isAdmin = is_debug();

if (file_exists($lockFilePath) && !$isAdmin) {
  echo date(DATE_RFC3339) . ' another process still running' . PHP_EOL;
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'respawn');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
  file_put_contents($statusFile, 'idle');
}

register_shutdown_function('exitProcess');

// limit execution time seconds unit
$startTime = microtime(true);
$maxExecutionTime = 120;
$db = new ProxyDB();

$proxies = $db->getDeadProxies(100);

foreach ($proxies as $item) {
  $open = isPortOpen($item['proxy']);
  $log = $item['proxy'] . ' port ' . ($open ? 'open' : 'closed') . PHP_EOL;
  echo $log;
  append_content_with_lock(__DIR__ . '/proxyChecker.txt', $log);
  if ($open) {
    $db->updateStatus($item['proxy'], 'untested');
  } else {
    // update last_check column
    $db->updateData($item['proxy'], ['last_check' => date(DATE_RFC3339)]);
  }
}
