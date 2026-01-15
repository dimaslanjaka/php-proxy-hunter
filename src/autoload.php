<?php

// Suppress PHP 8.4 deprecation notices about implicit nullable params
if (version_compare(PHP_VERSION, '8.4', '>=')) {
  error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
  ini_set('display_errors', '0');
}

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
$excludeFilenames = [
  'autoload.php',
];

$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator(__DIR__)
);
$loadedFiles = get_included_files();

foreach ($iterator as $file) {
  if ($file->isDir() || $file->getExtension() !== 'php' || $file->getRealPath() === __FILE__) {
    continue;
  }
  // Skip excluded filenames
  if (in_array($file->getFilename(), $excludeFilenames, true)) {
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

// Load any additional autoload.php files from xl subdirectories
foreach (glob(__DIR__ . '/../xl/*/autoload.php') as $autoloadFile) {
  if (is_file($autoloadFile)) {
    require_once $autoloadFile;
  }
}

// Verify

if (!function_exists('parseQueryOrPostBody')) {
  throw new RuntimeException('Function parseQueryOrPostBody() not found. Ensure func.php is included.');
}
if (!function_exists('is_debug_device')) {
  throw new RuntimeException('Function is_debug_device() not found. Ensure env.php is included.');
}
