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

if (in_array($_SERVER['HTTP_HOST'], $localhosts)) {
  // Read index.dev.html for local development
  $indexFile = __DIR__ . '/index.dev.html';
  if (file_exists($indexFile)) {
    readfile($indexFile);
    exit;
  }
} else {
  // Read index.html for production
  $indexFile = __DIR__ . '/dist/react/index.html';
  if (file_exists($indexFile)) {
    readfile($indexFile);
    exit;
  }
}
