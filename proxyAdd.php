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

require_once __DIR__ . '/php_backend/shared.php';

use PhpProxyHunter\FileLockHelper;

PhpProxyHunter\Server::allowCors(true);

header('Content-Type: application/json');

$userId     = getUserId();
$lock       = new FileLockHelper(tmp('locks/proxy-add-' . $userId . '.lock'));
$projectDir = __DIR__;
$filePath   = $projectDir . 'assets/proxies/added-' . $userId . '.txt';

ensure_dir(dirname($filePath));

$request = parseQueryOrPostBody();

// No data provided → return early
if (empty($request)) {
  header('Content-Type: application/json', true, 400);
  echo json_encode(['error' => true, 'message' => 'No data provided']);
  exit;
}

// Try lock
if (!$lock->lock(LOCK_EX)) {
  echo json_encode(['error' => true, 'message' => 'Another process is adding a proxy. Please try again later.']);
  exit;
}

// Lock acquired
$json             = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$extractedProxies = extractProxies($json);

// No valid proxy
if (count($extractedProxies) === 0) {
  echo json_encode(['error' => true, 'message' => 'No valid proxy found in the provided data']);
  $lock->release();
  exit;
}

// Convert proxy objects to string
$extractedProxiesAsString = array_map(function (\PhpProxyHunter\Proxy $item) {
  return (string) $item;
}, $extractedProxies);

// append to file
file_put_contents($filePath, implode(PHP_EOL, $extractedProxiesAsString) . PHP_EOL, FILE_APPEND | LOCK_EX);
// remove empty lines
removeEmptyLinesFromFile($filePath);
// remove duplicate lines
removeDuplicateLines($filePath);

// If proxyChecker.lock exists, merge into proxies.txt
$checkerLock = $projectDir . 'proxyChecker.lock';
if (file_exists($checkerLock)) {
  $newFilePath = $projectDir . '/proxies.txt';

  file_put_contents($newFilePath, implode(PHP_EOL, $extractedProxiesAsString) . PHP_EOL, FILE_APPEND | LOCK_EX);
  removeEmptyLinesFromFile($newFilePath);
  removeDuplicateLines($newFilePath);
}

echo json_encode(['error' => false, 'message' => 'Proxy added successfully']);

$lock->release();
