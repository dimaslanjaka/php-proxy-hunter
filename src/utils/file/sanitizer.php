<?php

/**
 * Sanitize a filename by removing any character that is not alphanumeric, underscore, dash, or period.
 *
 * @param string|null $filename The filename to sanitize.
 * @return string The sanitized filename.
 */
function sanitizeFilename(?string $filename): string
{
  if (empty($filename)) {
    $filename = '';
  }
  // Remove any character that is not alphanumeric, underscore, dash, or period
  $filename = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $filename);
  $filename = preg_replace('/-+/', '-', $filename);
  return $filename;
}

/**
 * Sanitize the filename in a given full path.
 *
 * This function will remove or replace unsafe characters in the filename part of the full path
 * while keeping the rest of the path intact. Unsafe characters that are typically sanitized include
 * spaces, special characters, and other non-alphanumeric characters that might cause issues in
 * file handling.
 *
 * @param string $fullPath The full path string containing the filename to sanitize.
 * @return string The full path with the sanitized filename.
 */
function sanitizeFilePath(string $fullPath): string
{
  // Extract the directory path and filename
  $pathParts = pathinfo($fullPath);

  // Define the sanitized filename using a regular expression to remove unsafe characters
  $sanitizedFilename = sanitizeFilename($pathParts['basename']);

  // Reconstruct the full path with the sanitized filename
  return $pathParts['dirname'] . DIRECTORY_SEPARATOR . $sanitizedFilename;
}
