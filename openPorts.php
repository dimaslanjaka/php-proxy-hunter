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
        removeStringAndMoveToFile($file, $testPath, trim($proxy));
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
