<?php

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\FileLockHelper;

PhpProxyHunter\Server::allowCors(true);

header('Content-Type: application/json');

$userId     = getUserId();
$lock       = new FileLockHelper(tmp('locks/proxy-add-' . $userId . '.lock'));
$projectDir = __DIR__ . '/../';
$filePath   = $projectDir . 'assets/proxies/added-' . $userId . '.txt';

ensure_dir(dirname($filePath));

$request = parseQueryOrPostBody();

// No data provided → return early
if (empty($request)) {
  header('Content-Type: application/json', true, 400);
  echo json_encode(['error' => true, 'message' => 'No data provided']);
  exit;
}

// Stop if locked
if ($lock->isLocked()) {
  echo json_encode(['error' => true, 'message' => 'Another process is adding a proxy. Please try again later.']);
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
