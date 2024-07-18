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

require_once __DIR__ . '/func-proxy.php';

use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Server;

global $isCli, $isWin;

// validate lock files
if (!$isCli) {
  $id = Server::getRequestIP();
  if (empty($id)) {
    $id = Server::useragent();
  }
} else {
  $id = 'CLI';
}
$lockFilePath = tmp() . "/runners/respawner-" . sanitizeFilename($id) . ".lock";
$statusFile = __DIR__ . "/status.txt";

if (!$isCli) {
  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: text/plain; charset=utf-8');
  if (isset($_REQUEST['uid'])) {
    setUserId($_REQUEST['uid']);
  }
  // only allow user with Google Analytics cookie
  if (!isset($_COOKIE['_ga'])) {
    exit('Access Denied');
  }
  // check admin
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

if (file_exists($lockFilePath) && !$isAdmin) {
  exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
} else {
  write_file($lockFilePath, date(DATE_RFC3339));
  write_file($statusFile, 'respawn');
}

function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
  write_file($statusFile, 'idle');
}

register_shutdown_function('exitProcess');

// limit execution time seconds unit
$startTime = microtime(true);
$maxExecutionTime = 120;
if ($isAdmin) {
  $maxExecutionTime = 10 * 60;
}
$db = new ProxyDB();
$pdo = $db->db->pdo;

if ($isCli) {
  $stmt = $pdo->prepare("SELECT *
    FROM proxies
    WHERE (status = 'port-closed' OR status = 'dead')
    AND last_check < datetime('now', '-7 days')
    ORDER BY RANDOM()
    LIMIT 100");
  $stmt->execute();
  $proxies = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($proxies as $item) {
    // Check if execution time has exceeded the maximum allowed time
    $elapsedTime = microtime(true) - $startTime;
    if ($elapsedTime > $maxExecutionTime) {
      break;
    } else {
      write_file($statusFile, "respawn");
    }
    $open = isPortOpen($item['proxy']);
    $log = "[RESPAWN] " . $item['proxy'] . ' port ' . ($open ? 'open' : 'closed') . PHP_EOL;
    echo $log;
    if ($open) {
      $db->updateData($item['proxy'], ['status' => 'untested']);
    } else {
      // update last_check column
      $db->updateData($item['proxy'], ['last_check' => date(DATE_RFC3339)]);
    }
  }
} else {
  // restart using CLI from web server
  $file = __FILE__;
  $output_file = __DIR__ . '/proxyChecker.txt';
  $cmd = "php " . escapeshellarg($file);
  // setup lock file
  $id = Server::getRequestIP();
  if (empty($id)) {
    $id = Server::useragent();
  }
  // lock file same as scanPorts.php
  $webLockFile = tmp() . '/runners/respawner-web-' . sanitizeFilename($id) . '.lock';

  $runner = tmp() . "/runners/" . basename($webLockFile, '.lock') . ($isWin ? '.bat' : "");
  $uid = getUserId();
  $cmd .= " --userId=" . escapeshellarg($uid);
  $cmd .= " --lockFile=" . escapeshellarg(unixPath($webLockFile));
  $cmd .= " --runner=" . escapeshellarg(unixPath($runner));
  $cmd .= " --max=" . escapeshellarg("30");
  $cmd .= " --admin=" . escapeshellarg($isAdmin ? 'true' : 'false');

  echo $cmd . "\n\n";

  // Generate the command to run in the background
  $cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($output_file), escapeshellarg($webLockFile));

  // Write the command to the runner script
  write_file($runner, $cmd);

  // Execute the runner script in the background
  runBashOrBatch($runner);
}
