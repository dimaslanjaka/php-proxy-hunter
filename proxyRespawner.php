<?php /** @noinspection PhpVariableIsUsedOnlyInClosureInspection */

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

use PhpProxyHunter\ProxyDB;

require_once __DIR__ . '/func-proxy.php';

// validate lock files
$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath)) {
  echo "another process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'respawn');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath)) unlink($lockFilePath);
  file_put_contents($statusFile, 'idle');
}

register_shutdown_function('exitProcess');

// limit execution time seconds unit
$startTime = microtime(true);
$maxExecutionTime = 120;
$proxyPaths = [__DIR__ . '/proxies-all.txt', __DIR__ . '/dead.txt', __DIR__ . '/proxies-backup.txt'];
$db = new ProxyDB();
iterateBigFilesLineByLine($proxyPaths, function (string $line) use ($startTime, $proxyPaths, $db, $maxExecutionTime) {
  $is_execution_exceeded = (microtime(true) - $startTime) > $maxExecutionTime;
  if (!$is_execution_exceeded) {
    $proxies = extractProxies($line);
    foreach ($proxies as $item) {
      $proxy = trim($item->proxy);
      if (!empty($proxy) && isValidProxy($proxy)) {
        if (isPortOpen($proxy)) {
          echo $proxy . ' respawned' . PHP_EOL;
          foreach ($proxyPaths as $file) {
            removeStringAndMoveToFile($file, __DIR__ . '/proxies.txt', trim($proxy));
          }
        }
      } else if (!empty($proxy)) {
        try {
          $db->remove($proxy);
          echo "deleted $proxy is invalid" . PHP_EOL;
        } catch (\Throwable $e) {
          echo "fail delete $proxy " . trim($e->getMessage()) . PHP_EOL;
        }
      }
    }
  }
});

