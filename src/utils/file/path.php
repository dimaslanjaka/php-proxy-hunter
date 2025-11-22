<?php

/**
 * Converts a file path to use Unix path separators (/).
 *
 * This function replaces all backslashes (\) with forward slashes (/)
 * in the given file path, ensuring it uses Unix-style separators.
 *
 * @param string $path The file path to convert.
 * @return string The file path with Unix separators.
 */
function unixPath(string $path): string {
  // Replace backslashes with forward slashes
  $unixPath = str_replace('\\', '/', $path);

  // Replace multiple slashes with a single slash
  $unixPath = preg_replace('#/{2,}#', '/', $unixPath);

  return $unixPath;
}
