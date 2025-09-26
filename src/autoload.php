<?php

if (defined('AUTOLOADER_INCLUDED')) {
  return;
}
define('AUTOLOADER_INCLUDED', 1);

// Auto load all php files including subdirectories

$excludeFolders = [
  '**/tmp/**',
  '**/simplehtmldom/**',
  '**/PhpProxyHunter/**',
  '**/mvc/**',
  '**/__*/**',
];

$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator(__DIR__)
);
$loadedFiles = [];

foreach ($iterator as $file) {
  if ($file->isDir() || $file->getExtension() !== 'php' || $file->getRealPath() === __FILE__) {
    continue;
  }
  // Skip files in excluded folders using glob patterns
  $skip     = false;
  $filePath = preg_replace('/\\\\+/', '/', $file->getPathname());
  foreach ($excludeFolders as $pattern) {
    if (fnmatch($pattern, $filePath)) {
      $skip = true;
      break;
    }
  }
  if ($skip) {
    continue;
  }

  $realPath = $file->getRealPath();
  // Skip if file already loaded
  if (in_array($realPath, $loadedFiles, true)) {
    continue;
  }

  if (
    $file->isFile()
  ) {
    require_once $realPath;
    $loadedFiles[] = $realPath;
  }
}

// Verify

if (!function_exists('parseQueryOrPostBody')) {
  throw new RuntimeException('Function parseQueryOrPostBody() not found. Ensure func.php is included.');
}
if (!function_exists('is_debug_device')) {
  throw new RuntimeException('Function is_debug_device() not found. Ensure env.php is included.');
}
