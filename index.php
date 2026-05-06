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
    'css'   => 'text/css',
    'js'    => 'application/javascript',
    'json'  => 'application/json',
    'txt'   => 'text/plain',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'png'   => 'image/png',
    'gif'   => 'image/gif',
    'bmp'   => 'image/bmp',
    'webp'  => 'image/webp',
    'svg'   => 'image/svg+xml',
    'ico'   => 'image/x-icon',
    'xml'   => 'application/xml',
    'xsl'   => 'application/xslt+xml',
    'jsonc' => 'application/json',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'otf'   => 'font/otf',
    'eot'   => 'application/vnd.ms-fontobject',
    'sfnt'  => 'font/sfnt',
    'font'  => 'font/ttf',
    'fnt'   => 'font/ttf',
  ];

  $requestPath = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
  $ext         = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));
  if (!array_key_exists($ext, $allowedExtensions)) {
    http_response_code(403);
    echo '403 Forbidden: The requested file type is not allowed.';
    exit;
  }

  // Try to serve from local root first, then from /dist/react
  $locations = [
    __DIR__ . $requestPath,
    __DIR__ . '/dist/react' . $requestPath,
  ];

  foreach ($locations as $file) {
    if (file_exists($file)) {
      $mimeType = $allowedExtensions[$ext];
      if ($mimeType === null) {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
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

// Handle php extension file not found
if (strpos($_SERVER['REQUEST_URI'], '.php') !== false) {
  $filePath = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  if (!file_exists($filePath)) {
    http_response_code(404);
    if (!headers_sent()) {
      header('Content-Type: text/plain');
    }
    echo '404 Not Found: The requested PHP file ' . htmlspecialchars($_SERVER['REQUEST_URI']) . ' was not found.';
    exit;
  }
}

// Maintenance mode check
$maintenanceFile = __DIR__ . '/tmp/locks/.build-lock';
if (file_exists($maintenanceFile)) {
  readfile(__DIR__ . '/index.maintenance.html');
  exit;
}

if (php_sapi_name() === 'cli') {
  $options = getopt('', ['set-maintenance', 'clear-maintenance']);
  if (isset($options['set-maintenance'])) {
    file_put_contents($maintenanceFile, 'Maintenance mode enabled at ' . date('Y-m-d H:i:s'));
    echo "Maintenance mode enabled.\n";
  } elseif (isset($options['clear-maintenance'])) {
    if (file_exists($maintenanceFile)) {
      unlink($maintenanceFile);
      echo "Maintenance mode disabled.\n";
    } else {
      echo "Maintenance mode is not enabled.\n";
    }
  }
  exit;
}

$distIndexFile = __DIR__ . '/dist/react/index.html';
if (file_exists($distIndexFile)) {
  readfile($distIndexFile);
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
