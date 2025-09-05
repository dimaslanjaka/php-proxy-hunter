<?php

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

foreach ($iterator as $file) {
  if ($file->isDir() || $file->getExtension() !== 'php' || $file->getRealPath() === __FILE__) {
    continue;
  }
  // Skip files in excluded folders using glob patterns
  $skip     = false;
  $filePath = str_replace('\\', '/', $file->getPathname());
  foreach ($excludeFolders as $pattern) {
    if (fnmatch($pattern, $filePath)) {
      $skip = true;
      break;
    }
  }
  if ($skip) {
    continue;
  }

  if (
    $file->isFile()
  ) {
    require_once $file->getRealPath();
  }
}
