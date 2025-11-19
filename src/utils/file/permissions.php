<?php

/**
 * Sets file permissions to 777 if the file exists.
 *
 * @param string|array $filenames The filename(s) to set permissions for.
 *                                Can be a single filename or an array of filenames.
 * @param bool $autoCreate (optional) Whether to automatically create the file if it doesn't exist. Default is false.
 *
 * @return void
 */
function setMultiPermissions($filenames, bool $autoCreate = false) {
  if (is_array($filenames)) {
    foreach ($filenames as $filename) {
      setPermissions($filename, $autoCreate);
    }
  } elseif (is_string($filenames)) {
    setPermissions($filenames, $autoCreate);
  } else {
    echo "Invalid parameter type. Expected string or array.\n";
  }
}

/**
 * Sets file permissions to 777 if the file exists.
 *
 * @param string $filename The filename to set permissions for.
 * @param bool $autoCreate (optional) Whether to automatically create the file if it doesn't exist. Default is false.
 *
 * @return bool Returns true if the permissions were successfully set, false otherwise.
 */
function setPermissions(string $filename, bool $autoCreate = false): bool {
  try {
    if (!file_exists($filename) && $autoCreate) {
      write_file($filename, '');
    }
    if (file_exists($filename) && is_readable($filename) && is_writable($filename)) {
      return @chmod($filename, 0777);
    }
  } catch (Throwable $th) {
    return false;
  }
  return false;
}
