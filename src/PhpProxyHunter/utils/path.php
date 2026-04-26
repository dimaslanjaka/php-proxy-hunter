<?php

/**
 * Convert a path to a Unix-style path and normalize separators.
 *
 * Accepts one or more path segments (variadic). Segments are joined using
 * the platform DIRECTORY_SEPARATOR before normalization. Backslashes are
 * converted to forward slashes and Windows drive letters are converted to
 * a leading '/<Letter>' (e.g. C:\ -> /C).
 *
 * Examples:
 *   toUnixPath('C:\\', 'path', 'file.txt') => '/C/path/file.txt'
 *   toUnixPath('/var', 'log') => '/var/log'
 *
 * @param string ...$parts Path segments to join and normalize
 * @return string Normalized Unix-style path (empty string if no parts)
 */
function toUnixPath(...$parts) {
  if (count($parts) === 0) {
    return '';
  }

  $path = implode(DIRECTORY_SEPARATOR, $parts);
  $path = str_replace('\\', '/', $path);
  return preg_replace('/^([A-Za-z]):/', '/$1', $path);
}
