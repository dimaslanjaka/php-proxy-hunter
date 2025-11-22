<?php

/** Ensure directory exists helper (keeps behavior but avoids repeating mkdir checks)
 *
 * Accepts one or more path segments. If multiple segments are provided,
 * they are joined using DIRECTORY_SEPARATOR and normalized. The function
 * will attempt to create the directory (recursive). On success it returns
 * the resulting directory path. On failure it returns false.
 *
 * @param string ...$segments One or more path segments to join into the dir
 * @return string|false The created directory path on success, or false on failure
 */
function ensure_dir(...$segments) {
  // If no segments provided, nothing to do
  if (count($segments) === 0) {
    return false;
  }

  // Join segments with DIRECTORY_SEPARATOR but avoid duplicate separators
  $parts = [];
  foreach ($segments as $seg) {
    if ($seg === null || $seg === '') {
      continue;
    }
    // Normalize backslashes to forward slashes first
    $seg = str_replace('\\', '/', $seg);
    $seg = trim($seg, "/ \\\n\r\t");
    if ($seg === '') {
      continue;
    }
    $parts[] = $seg;
  }

  if (count($parts) === 0) {
    return false;
  }

  // Build path using forward slashes then normalize to DIRECTORY_SEPARATOR
  $path = implode('/', $parts);
  // Collapse repeated slashes
  $path = preg_replace('#/{2,}#', '/', $path);

  // If absolute on Windows (C:/) or Unix (/) preserve leading slash or drive
  if (preg_match('#^[A-Za-z]:/#', $path) || strpos($path, '/') === 0) {
    // keep as-is
  } else {
    // keep relative
  }

  // Convert to platform directory separators
  $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

  if (!is_dir($path)) {
    if (!@mkdir($path, 0777, true)) {
      return false;
    }
  }

  return $path;
}


/**
 * Ensure multiple directories exist.
 *
 * Accepts multiple directory paths (each path may be a single string or an array of segments).
 * For each provided argument it will attempt to create the directory and return a
 * map of the original input => created path (or false on failure).
 *
 * Examples:
 *   ensure_dirs('a/b', 'c/d');
 *   ensure_dirs(['a','b'], 'c/d');
 *
 * @param mixed ...$dirs Variable list of directories. Each item can be a string or array of path segments.
 * @return array<int, string|false> Associative array with keys as provided index and values as created path or false.
 */
function ensure_dirs(...$dirs): array {
  $results = [];

  foreach ($dirs as $idx => $dir) {
    if (is_array($dir)) {
      // Pass array segments to ensure_dir using splat
      $res = ensure_dir(...$dir);
    } else {
      // Single string path
      $res = ensure_dir($dir);
    }
    $results[$idx] = $res;
  }

  return $results;
}

/**
 * Create parent folders for a file path if they don't exist.
 *
 * @param string $filePath The file path for which to create parent folders.
 *
 * @return bool True if all parent folders were created successfully or already exist, false otherwise.
 */
function createParentFolders(string $filePath): bool {
  $parentDir = dirname($filePath);

  // Check if the parent directory already exists
  if (!is_dir($parentDir)) {
    // Attempt to create the parent directory and any necessary intermediate directories
    if (!mkdir($parentDir, 0777, true)) {
      // Failed to create the directory
      error_log("Failed to create directory: $parentDir");
      return false;
    }

    // Set permissions for the parent directory
    if (!chmod($parentDir, 0777)) {
      error_log("Failed to set permissions for directory: $parentDir");
      return false;
    }
  }

  return true;
}
