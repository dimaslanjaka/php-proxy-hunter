<?php

// Auto load all php files including subdirectories

$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator(__DIR__)
);

foreach ($iterator as $file) {
  if (
    $file->isFile() &&
    $file->getExtension() === 'php' &&
    $file->getRealPath() !== __FILE__
  ) {
    require_once $file->getRealPath();
  }
}
