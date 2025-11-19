<?php

$excludes = [
  'autoload.php',
];

// Directly require all custom fixer files in this directory (exclude this file).
// NOTE: This intentionally removes the SPL autoloader behavior and eagerly
// includes every PHP file here. This is useful for CLI/debug runs or when
// you want all fixers loaded unconditionally.
foreach (glob(__DIR__ . '/*.php') as $f) {
  // Skip this file and any excludes
  if (in_array(basename($f), $excludes, true)) {
    continue;
  }
  require_once $f;
}

// debug autoload
// var_dump(get_declared_classes());
// $loadedFiles = get_included_files();
// var_dump($loadedFiles);
