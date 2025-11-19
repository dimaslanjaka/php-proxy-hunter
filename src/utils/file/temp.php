<?php

/**
 * Get project temp folder.
 *
 * Build and return the path to the project's temp directory. Additional
 * path segments can be provided as variadic arguments and will be appended
 * to the base tmp folder. The function will create the directory (recursively)
 * if it does not exist and attempt to set safe permissions (0755).
 *
 * Example:
 *   tmp('cache', 'session'); // => /path/to/project/tmp/cache/session
 *
 * @param string ...$args Optional path segments to append to the tmp directory.
 * @return string|false Absolute path to the resulting temporary directory on success, or false on failure.
 *
 * Failure reasons:
 * - getProjectRoot() throws or cannot be resolved.
 * - Failed to create the directory (mkdir failure).
 * - Failed to set permissions (chmod failure).
 */
function tmp(...$args) {
  $projectRoot = getProjectRoot();

  // Base directory for temp folder
  $tmpDir = $projectRoot . '/tmp';

  // Append additional directories from $args
  foreach ($args as $arg) {
    $tmpDir .= '/' . ltrim($arg, '/');  // Ensure no leading slashes
  }

  // Create temp folder if it doesn't exist
  if (!file_exists($tmpDir)) {
    if (!mkdir($tmpDir, 0755, true)) {
      // Log an error and return false if folder creation fails
      error_log("Failed to create directory: $tmpDir");
      return false;
    }
  }

  // Set permissions for the directory, avoid 0777
  if (!chmod($tmpDir, 0755)) {
    // Log an error if setting permissions fails
    error_log("Failed to set permissions for directory: $tmpDir");
    return false;
  }

  return $tmpDir;
}
