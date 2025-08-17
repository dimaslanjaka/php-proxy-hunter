<?php

// Enable CORS for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: *');

$localhosts = ['localhost', '127.0.0.1', 'dev.webmanajemen.com', 'php.webmanajemen.com'];

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
    'jsonc',
    'js',
    'css',
    'woff',
    'woff2',
    'ttf',
    'otf',
    'eot',
    'sfnt',
    'font',
    'fnt'
  ];

  $requestPath = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
  $ext = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExtensions, true)) {
    http_response_code(403);
    echo '403 Forbidden: The requested file type is not allowed.';
    exit;
  }

  // Try to serve from local root first, then from /dist/react
  $locations = [
    __DIR__ . $requestPath,
    __DIR__ . '/dist/react' . $requestPath
  ];

  foreach ($locations as $file) {
    if (file_exists($file)) {
      if ($ext === 'js') {
        $mimeType = 'application/javascript';
      } elseif ($ext === 'css') {
        $mimeType = 'text/css';
      } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file);
        finfo_close($finfo);
      }
      header('Content-Type: ' . $mimeType);
      readfile($file);
      exit;
    }
  }

  http_response_code(404);
  echo '404 Not Found: The requested file ' . htmlspecialchars($_SERVER['REQUEST_URI']) . ' was not found.';
  exit;
}

$indexFile = __DIR__ . '/index.html';
if (file_exists($indexFile)) {
  readfile($indexFile);
  exit;
} else {
  http_response_code(404);
  echo '404 Not Found: The main index.html file was not found in the application root directory.';
  exit;
}
