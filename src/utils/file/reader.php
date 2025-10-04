<?php

/**
 * Read a specific number of bytes from a file starting at a given offset.
 *
 * This function allows partial reading of large files without loading them fully into memory.
 *
 * @param string $filename Path to the file.
 * @param int    $length   Number of bytes to read (default: 4096 = 4KB).
 * @param int    $offset   Position in the file to start reading (default: 0).
 *
 * @return string|false Returns the read data as a string, or false on failure.
 */
function readFileChunk($filename, $length = 4096, $offset = 0)
{
  if (!is_readable($filename)) {
    return false;
  }

  $handle = fopen($filename, 'rb');
  if ($handle === false) {
    return false;
  }

  if ($offset > 0) {
    fseek($handle, $offset);
  }

  $data = fread($handle, $length);

  fclose($handle);

  return ($data !== false) ? $data : false;
}
