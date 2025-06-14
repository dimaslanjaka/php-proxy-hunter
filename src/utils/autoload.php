<?php

// Get the full path of the executing script
$caller = PHP_SAPI === 'cli' && isset($_SERVER['argv'][0])
  ? realpath($_SERVER['argv'][0])
  : (isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : null);

// Load all .php files from current dir except this file and caller
$files = glob(__DIR__ . '/*.php');

foreach ($files as $file) {
  $realFile = realpath($file);
  if ($realFile !== __FILE__ && $realFile !== $caller) {
    require_once $realFile;
  }
}
