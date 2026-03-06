<?php

require_once __DIR__ . '/../func.php';

header('Content-Type: application/json');

$file = __DIR__ . '/../tmp/data/tailscale.json';
if (!file_exists($file)) {
  echo json_encode(['error' => true, 'message' => "No data file found at $file"]);
  exit(1);
}

$content = read_file($file);
if ($content === false || trim($content) === '') {
  echo json_encode(['error' => true, 'message' => "No data read from file $file"]);
  exit(1);
}

// Try to decode JSON from the file. Always return an envelope with `error` boolean.
$decoded = json_decode($content, true);
if (json_last_error() === JSON_ERROR_NONE) {
  echo json_encode(['error' => false, 'data' => $decoded]);
  exit(0);
}

// Otherwise wrap the raw content in a JSON response.
echo json_encode(['error' => false, 'data' => $content]);
exit(0);
