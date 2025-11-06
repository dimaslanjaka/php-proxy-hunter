<?php

/**
 * autoload.php
 * Automatically loads all PHP files in the same directory (and all subdirectories).
 */

$directory = __DIR__;

if (!function_exists('autoloadAllPHP')) {
  /**
   * Recursively include all PHP files in a directory
   *
   * @param string $dir
   * @return void
   */
  function autoloadAllPHP($dir) {
    // Scan the directory
    $files = scandir($dir);

    foreach ($files as $file) {
      $path = $dir . DIRECTORY_SEPARATOR . $file;

      if ($file === '.' || $file === '..') {
        continue;
      }

      // Skip this autoload file to prevent infinite loops
      if ($file === basename(__FILE__)) {
        continue;
      }

      // echo "Autoloading file: " . $path . PHP_EOL;

      if (is_dir($path)) {
        // Recurse into subdirectory
        autoloadAllPHP($path);
      } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
        // Include PHP file
        require_once $path;
      }
    }
  }
}

// Start autoloading
autoloadAllPHP($directory);

// debug all loaded files when called directly
// if (basename(__FILE__) === 'autoload.php' && php_sapi_name() === 'cli') {
//   $includedFiles = get_included_files();
//   echo "Included PHP files:\n";
//   foreach ($includedFiles as $file) {
//     echo "- " . $file . "\n";
//   }
// }
