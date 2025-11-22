<?php

require_once __DIR__ . '/../../func.php';

header('Content-Type: text/plain');

// Example usage
$path    = '/logs/example.log';
$fullUrl = getFullUrl($path);
echo 'Full URL for non-absolute path: ' . $fullUrl . PHP_EOL;

$path    = __FILE__;
$fullUrl = getFullUrl($path);
echo 'Full URL for current file: ' . $fullUrl . PHP_EOL;
