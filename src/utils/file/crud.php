<?php

/**
 * Reads a file as UTF-8 encoded text.
 *
 * @param string $inputFile The path to the input file.
 * @param int $chunkSize The size of each chunk to read in bytes. Default is 1 MB = 1048576 bytes.
 * @return string|false The content of the file or false on failure.
 */
function read_file(string $inputFile, int $chunkSize = 1048576) {
  if (!file_exists($inputFile)) {
    return false;
  }
  $isReadable = is_readable($inputFile);
  $isLocked   = is_file_locked($inputFile);
  if (!$isReadable || $isLocked) {
    $trace          = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
    $caller         = isset($trace[1]) ? $trace[1] : (isset($trace[0]) ? $trace[0] : []);
    $callerFile     = isset($caller['file']) ? $caller['file'] : 'unknown';
    $callerLine     = isset($caller['line']) ? $caller['line'] : 'unknown';
    $callerClass    = isset($caller['class']) ? $caller['class'] : 'non class';
    $callerFunction = isset($caller['function']) ? $caller['function'] : 'non function';

    if (!$isReadable) {
      var_dump(getFileInfo($inputFile));
      echo "$inputFile is not readable." . PHP_EOL;
      echo "Called by $callerClass->$callerFunction in {$callerFile} on line {$callerLine}" . PHP_EOL;
    }
    return false;
  }

  $content = '';
  $handle  = @fopen($inputFile, 'rb');

  if ($handle === false) {
    echo "Failed to open $inputFile for reading." . PHP_EOL;
    return false;
  }

  while (!feof($handle)) {
    $chunk = fread($handle, $chunkSize);
    if ($chunk === false) {
      echo "Error reading from $inputFile." . PHP_EOL;
      fclose($handle);
      return false;
    }
    $content .= $chunk;
  }

  fclose($handle);
  return $content;
}

/**
 * Write data to a file and create the parent folder if it doesn't exist.
 *
 * @param string $inputFile The path to the file.
 * @param string $data The data to write to the file.
 * @return bool True on success, false on failure.
 */
function write_file(string $inputFile, string $data): bool {
  // skip writing locked file
  if (file_exists($inputFile) && is_file_locked($inputFile)) {
    return false;
  }

  // Get the directory name from the file path
  $dir = dirname($inputFile);

  // Create the parent folder if it doesn't exist
  if (!is_dir($dir)) {
    if (!mkdir($dir, 0777, true)) {
      // Failed to create the directory
      return false;
    }
  }

  try {
    // Write data to the file
    if (file_put_contents($inputFile, $data) !== false) {
      setMultiPermissions($inputFile);
      // Successfully wrote the file
      return true;
    } else {
      // Failed to write the file
      return false;
    }
  } catch (\Throwable $th) {
    return false;
  }
}

/**
 * Deletes a file or directory. Supports deleting multiple files or directories.
 *
 * @param string|array $path The path(s) to the file(s) or directory(ies) to be deleted.
 * @return array An associative array with 'deleted' and 'errors' keys indicating the paths deleted and any errors encountered.
 */
function delete_path($path): array {
  $result = [
    'deleted' => [],
    'errors'  => [],
  ];

  // Convert single path to an array for uniform handling
  if (is_string($path)) {
    $paths = [$path];
  } elseif (is_array($path)) {
    $paths = $path;
  } else {
    $result['errors'][] = 'Path must be a string or an array of strings.';
    return $result;
  }

  foreach ($paths as $p) {
    // Check if the path exists
    if (file_exists($p)) {
      if (is_dir($p)) {
        // Recursively delete directory contents before deleting the directory
        $files = array_diff(scandir($p), ['.', '..']);
        foreach ($files as $file) {
          $sub_result        = delete_path("$p/$file");
          $result['deleted'] = array_merge($result['deleted'], $sub_result['deleted']);
          $result['errors']  = array_merge($result['errors'], $sub_result['errors']);
        }
        // Remove the directory
        if (rmdir($p)) {
          $result['deleted'][] = $p;
        } else {
          $result['errors'][] = "Failed to delete directory: $p";
        }
      } else {
        // Delete the file
        if (unlink($p)) {
          $result['deleted'][] = $p;
        } else {
          $result['errors'][] = "Failed to delete file: $p";
        }
      }
    } else {
      $result['errors'][] = "Path does not exist: $p";
    }
  }

  return $result;
}
