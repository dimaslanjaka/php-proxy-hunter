<?php

include __DIR__ . '/shared.php';

PhpProxyHunter\Server::allowCors();

$filePath = __DIR__ . '/../assets/proxies/added-' . getUserId() . '.txt';
ensure_dir(dirname($filePath));
$request = parseQueryOrPostBody();
if (!empty($request)) {
  $json = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  // append to file
  file_put_contents($filePath, $json . PHP_EOL, FILE_APPEND | LOCK_EX);

  header('Content-Type: application/json');
  echo json_encode(['status' => 'ok']);
} else {
  header('Content-Type: application/json', true, 400);
  echo json_encode(['error' => true, 'message' => 'No data provided']);
}
