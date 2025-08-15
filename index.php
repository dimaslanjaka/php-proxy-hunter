<?php

// Enable CORS for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$localhosts = ['localhost', '127.0.0.1', 'dev.webmanajemen.com'];

if (in_array($_SERVER['HTTP_HOST'], $localhosts)) {
  // Enable error reporting for local development
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
} else {
  // Disable error reporting for production
  error_reporting(0);
  ini_set('display_errors', 0);
}

// Route /assets and /data to dist/assets and dist/data with auto MIME type, allow only specific file types
if (strpos($_SERVER['REQUEST_URI'], '/assets/') === 0 || strpos($_SERVER['REQUEST_URI'], '/data/') === 0) {
  $filePath = __DIR__ . '/dist' . $_SERVER['REQUEST_URI'];
  $allowedExtensions = [
    'json',
    'txt',
    'jpg',
    'jpeg',
    'png',
    'gif',
    'bmp',
    'webp',
    'svg',
    'ico',
    'xml',
    'xsl',
    'jsonc'
  ];
  $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExtensions, true)) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
  }
  if (file_exists($filePath)) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    header('Content-Type: ' . $mimeType);
    readfile($filePath);
    exit;
  } else {
    http_response_code(404);
    echo '404 Not Found';
    exit;
  }
}

$indexFile = __DIR__ . '/index.html';
if (file_exists($indexFile)) {
  readfile($indexFile);
  exit;
} else {
  http_response_code(404);
  echo '404 Not Found';
  exit;
}
