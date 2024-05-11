<?php

require_once __DIR__ . '/func.php';

$file = 'proxies.txt';

if (isset($_REQUEST['file'])) {
  $file = rawurldecode(trim($_REQUEST['file']));
}

$real_file = realpath(__DIR__ . '/' . $file);

if ($real_file && file_exists($real_file)) {
  // Determine the Content-Type based on the file extension
  $fileExtension = pathinfo($real_file, PATHINFO_EXTENSION);
  $contentType = mime_content_type($real_file);

  // Set the appropriate Content-Type header
  header("Content-Type: $contentType");

  // Read the file and echo its contents
  readfile($real_file);
} else {
  // File not found or inaccessible
  http_response_code(404);
  echo "$file not found";
}
