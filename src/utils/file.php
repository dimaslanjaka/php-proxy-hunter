<?php


/**
 * Get file information including permissions, owner, and group.
 *
 * @param string $file The file path.
 * @return object An object containing file details: permissions, owner, and group.
 */
function getFileInfo(string $file)
{
  if (!file_exists($file)) {
    return (object)[
      'error' => 'File does not exist.'
    ];
  }

  // Get file permissions
  $permissions = fileperms($file);
  $permissionsFormatted = substr(sprintf('%o', $permissions), -4);

  // Get file owner
  $owner = fileowner($file);
  $owner_info = posix_getpwuid($owner);
  $ownerName = $owner_info['name'];

  // Get file group
  $group = filegroup($file);
  $group_info = posix_getgrgid($group);
  $groupName = $group_info['name'];

  return [
    'file' => $file,
    'permissions' => $permissionsFormatted,
    'owner' => $ownerName,
    'group' => $groupName
  ];
}

/**
 * Reads a file as UTF-8 encoded text.
 *
 * @param string $inputFile The path to the input file.
 * @param int $chunkSize The size of each chunk to read in bytes. Default is 1 MB = 1048576 bytes.
 * @return string|false The content of the file or false on failure.
 */
function read_file(string $inputFile, int $chunkSize = 1048576)
{
  if (!file_exists($inputFile)) {
    return false;
  }
  $isReadable = is_readable($inputFile);
  $isLocked = is_file_locked($inputFile);
  if (!$isReadable || $isLocked) {
    $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
    $caller = isset($trace[1]) ? $trace[1] : (isset($trace[0]) ? $trace[0] : []);
    $callerFile = isset($caller['file']) ? $caller['file'] : 'unknown';
    $callerLine = isset($caller['line']) ? $caller['line'] : 'unknown';
    $callerClass = isset($caller['class']) ? $caller['class'] : 'non class';
    $callerFunction = isset($caller['function']) ? $caller['function'] : 'non function';

    if (!$isReadable) {
      var_dump(getFileInfo($inputFile));
      echo "$inputFile is not readable." . PHP_EOL;
      echo "Called by $callerClass->$callerFunction in {$callerFile} on line {$callerLine}" . PHP_EOL;
    }
    return false;
  }

  $content = '';
  $handle = @fopen($inputFile, 'rb');

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
function write_file(string $inputFile, string $data): bool
{
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
function delete_path($path): array
{
  $result = [
    'deleted' => [],
    'errors' => []
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
          $sub_result = delete_path("$p/$file");
          $result['deleted'] = array_merge($result['deleted'], $sub_result['deleted']);
          $result['errors'] = array_merge($result['errors'], $sub_result['errors']);
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

/**
 * Converts a file path to use Unix path separators (/).
 *
 * This function replaces all backslashes (\) with forward slashes (/)
 * in the given file path, ensuring it uses Unix-style separators.
 *
 * @param string $path The file path to convert.
 * @return string The file path with Unix separators.
 */
function unixPath($path)
{
  // Replace backslashes with forward slashes
  $unixPath = str_replace('\\', '/', $path);

  // Replace multiple slashes with a single slash
  $unixPath = preg_replace('#/{2,}#', '/', $unixPath);

  return $unixPath;
}

/**
 * Checks if a file is locked by another PHP process or is temporarily unavailable.
 *
 * @param string $filePath The path to the file.
 * @return bool True if the file is locked or temporarily unavailable, false otherwise.
 */
function is_file_locked(string $filePath): bool
{
  // Check if the file exists
  if (!file_exists($filePath) || !is_readable($filePath) || !is_writable($filePath)) {
    return true;
  }

  // Check if the file is locked
  $handle = @fopen($filePath, "r");
  if ($handle === false) {
    // Unable to open file
    return true;
  }

  // Attempt to acquire an exclusive lock
  $isLocked = !flock($handle, LOCK_EX | LOCK_NB);

  // Release the lock
  flock($handle, LOCK_UN);
  fclose($handle);

  return $isLocked;
}

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
  $filename = preg_replace("/[^a-zA-Z0-9_-]+/", '-', $filename);
  $filename = preg_replace("/-+/", '-', $filename);
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
/**
 * Returns the absolute path to the project root directory.
 *
 * This function locates the Composer autoloader file using reflection,
 * then traverses up three directory levels to determine the root of the project.
 *
 * @return string The absolute path to the project root directory.
 *
 * @throws \ReflectionException If the Composer autoloader class cannot be found.
 */

function getProjectRoot(): string
{
  $autoloadPath = (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName();
  return unixPath(dirname(dirname(dirname($autoloadPath))));
}

/**
 * Get project temp folder.
 *
 * @return string|false The temporary directory path or false on failure.
 */
function tmp(...$args)
{
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

/**
 * Append content to a file with file locking.
 *
 * @param string $file The file path.
 * @param string $content_to_append The content to append.
 * @return bool True if the content was successfully appended, false otherwise.
 */
function append_content_with_lock(string $file, string $content_to_append): bool
{
  createParentFolders($file);
  if (file_exists($file)) {
    if (is_file_locked($file)) {
      return false;
    }
    if (!is_writable($file)) {
      return false;
    }
  }
  // Open the file for appending, create it if it doesn't exist
  $handle = @fopen($file, 'a+');

  // Check if file handle is valid
  if (!$handle) {
    return false;
  }

  // Acquire an exclusive lock
  if (flock($handle, LOCK_EX)) {
    // Append the content
    if (!empty(trim($content_to_append))) {
      fwrite($handle, $content_to_append);
    }

    // Release the lock
    flock($handle, LOCK_UN);

    // Close the file handle
    fclose($handle);

    return true;
  } else {
    // Couldn't acquire the lock
    fclose($handle);
    return false;
  }
}
