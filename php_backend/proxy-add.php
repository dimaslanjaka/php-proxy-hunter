<?php

require_once __DIR__ . '/shared.php';

PhpProxyHunter\Server::allowCors();
use PhpProxyHunter\FileLockHelper;

header('Content-Type: application/json');

$userId = getUserId();
$lock   = new FileLockHelper(tmp('locks/proxy-add-' . $userId . '.lock'));

$filePath = __DIR__ . '/../assets/proxies/added-' . $userId . '.txt';
ensure_dir(dirname($filePath));

$request = parseQueryOrPostBody();
if (!empty($request)) {
  if ($lock->lock(LOCK_EX)) {
    $json             = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $extractedProxies = extractProxies($json);
    if (count($extractedProxies) === 0) {
      echo json_encode(['error' => true, 'message' => 'No valid proxy found in the provided data']);
      $lock->release();
      exit;
    }

    $extractedProxiesAsString = array_map(function (\PhpProxyHunter\Proxy $item) {
      return (string) $item;
    }, $extractedProxies);

    // append to file
    file_put_contents($filePath, implode(PHP_EOL, $extractedProxiesAsString) . PHP_EOL, FILE_APPEND | LOCK_EX);
    // remove empty lines
    removeEmptyLinesFromFile($filePath);
    // remove duplicate lines
    removeDuplicateLines($filePath);

    echo json_encode(['error' => false, 'message' => 'Proxy added successfully']);

    $lock->release();
  } else {
    echo json_encode(['error' => true, 'message' => 'Another process is adding a proxy. Please try again later.']);
  }
} else {
  header('Content-Type: application/json', true, 400);
  echo json_encode(['error' => true, 'message' => 'No data provided']);
}
