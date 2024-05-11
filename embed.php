<?php

require_once __DIR__ . '/func.php';

$file = 'proxies.txt';

if (isset($_REQUEST['file'])) {
  $file = rawurldecode(trim($_REQUEST['file']));
}

$real_file = realpath(__DIR__ . '/' . $file);

if ($real_file && file_exists($real_file)) {
  // Determine the file extension
  $fileExtension = pathinfo($real_file, PATHINFO_EXTENSION);

  // Check if the file extension is allowed (json or txt)
  if ($fileExtension === 'json' || $fileExtension === 'txt') {
    // Determine the Content-Type based on the file extension
    $contentType = mime_content_type($real_file);

    // Set the appropriate Content-Type header
    header("Content-Type: $contentType");

    // Read the file and echo its contents
    readfile($real_file);
  } else {
    // Invalid file type
    http_response_code(400);
    echo "Invalid file type. Only JSON and text files are allowed.";
  }
} else {
  // File not found or inaccessible
  http_response_code(404);
  echo "$file not found";
}
