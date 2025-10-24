<?php

include __DIR__ . '/shared.php';

if (!is_cli()) {
  // CORS support: allow cross-origin requests and handle preflight OPTIONS
  // This must run before any output is sent.
  if (isset($_SERVER['HTTP_ORIGIN'])) {
    // Allow the requesting origin. You can restrict this to a whitelist.
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // cache for 1 day
  } else {
    // Fallback to allow any origin if HTTP_ORIGIN is not provided
    header('Access-Control-Allow-Origin: *');
  }

  // Handle preflight requests
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
      header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
    }

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
      header('Access-Control-Allow-Headers: ' . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
    }

    // Preflight response is empty but must be 200
    http_response_code(200);
    exit();
  }
}

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
