<?php

if (!file_exists(__DIR__ . '/executables.json')) {
  // If the executables.json file does not exist, attempt to generate it
  $nodePath  = 'node';
  $script    = realpath(__DIR__ . '/executables-finder.cjs');
  $output    = null;
  $returnVar = null;
  exec(escapeshellcmd($nodePath) . ' ' . escapeshellarg($script), $output, $returnVar);
  if ($returnVar !== 0) {
    throw new RuntimeException('Failed to generate executables.json using Node.js script.');
  }
}

/**
 * Get the configured PHP executable path from executables.json.
 *
 * This function reads the executables.json file located in the same directory,
 * decodes it as JSON and returns the value for the "php" key when present.
 *
 * If the file cannot be read or the "php" key is not present, this function
 * returns null. Note that file_get_contents and json_decode may emit warnings
 * on failure; callers should handle null return values accordingly.
 *
 * @return string|null Absolute path to the PHP executable, or null if not found.
 */
function getPhpExecutable($escape = false) {
  $json    = file_get_contents(__DIR__ . '/executables.json');
  $data    = json_decode($json, true);
  $phpPath = isset($data['php']) ? $data['php'] : null;
  return $escape && $phpPath ? escapeshellcmd($phpPath) : $phpPath;
}

/**
 * Get the configured Python executable path from executables.json.
 *
 * This function reads the executables.json file located in the same directory,
 * decodes it as JSON and returns the value for the "python" key when present.
 *
 * If the file cannot be read or the "python" key is not present, this function
 * returns null. Note that file_get_contents and json_decode may emit warnings
 * on failure; callers should handle null return values accordingly.
 *
 * @return string|null Absolute path to the Python executable, or null if not found.
 */
function getPythonExecutable($escape = false) {
  $json       = file_get_contents(__DIR__ . '/executables.json');
  $data       = json_decode($json, true);
  $pythonPath = isset($data['python']) ? $data['python'] : null;
  return $escape && $pythonPath ? escapeshellcmd($pythonPath) : $pythonPath;
}
