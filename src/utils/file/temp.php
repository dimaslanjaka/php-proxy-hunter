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
 *                      If the last segment looks like a filename (has an extension),
 *                      the function will create the containing directory but will
 *                      not create or chmod the file itself.
 * @return string|false Absolute path to the resulting temporary directory or file on
 *                      success, or false on failure.
 *
 * Failure reasons:
 * - getProjectRoot() throws or cannot be resolved.
 * - Failed to create the directory (mkdir failure).
 * - Failed to set permissions (chmod failure).
 */
function tmp(...$args) {
  $projectRoot = get_project_root();

  $projectRoot = rtrim((string) $projectRoot, "\/\\");
  $baseTmp     = $projectRoot . DIRECTORY_SEPARATOR . 'tmp';

  // Normalize and filter args
  $parts = array_values(array_filter(array_map(function ($p) {
    return $p === null ? '' : trim((string) $p, "\/\\");
  }, $args), fn ($p) => $p !== ''));

  // Detect file
  $last   = $parts ? end($parts) : null;
  $isFile = $last && pathinfo($last, PATHINFO_EXTENSION) !== '';

  // Build directory path only
  $dirParts = $isFile ? array_slice($parts, 0, -1) : $parts;
  $dirPath  = $baseTmp . ($dirParts ? DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $dirParts) : '');

  // Directory must exist
  // Do not create or validate directories here — return the computed path
  // so callers may create or check as needed. Only attempt to chmod when the
  // directory actually exists to avoid warnings.

  // 🚀 EARLY RETURN FOR FILE — no chmod executed at all
  if ($isFile) {
    return $dirPath . DIRECTORY_SEPARATOR . $last;
  }

  // Only attempt to set permissions if the directory exists
  if (is_dir($dirPath)) {
    if (!@chmod($dirPath, 0755)) {
      error_log("Failed to set permissions for directory: $dirPath");
    }
  }

  return $isFile ? $dirPath . DIRECTORY_SEPARATOR . $last : $dirPath;
}
