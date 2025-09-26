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
      'error' => 'File does not exist.',
    ];
  }

  // Get file permissions
  $permissions          = fileperms($file);
  $permissionsFormatted = substr(sprintf('%o', $permissions), -4);

  // Get file owner
  $owner      = fileowner($file);
  $owner_info = posix_getpwuid($owner);
  $ownerName  = $owner_info['name'];

  // Get file group
  $group      = filegroup($file);
  $group_info = posix_getgrgid($group);
  $groupName  = $group_info['name'];

  return [
    'file'        => $file,
    'permissions' => $permissionsFormatted,
    'owner'       => $ownerName,
    'group'       => $groupName,
  ];
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
  $handle = @fopen($filePath, 'r');
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

/**
 * Removes empty lines from a text file.
 *
 * @param string $filePath The path to the text file.
 * @return void
 */
function removeEmptyLinesFromFile(string $filePath)
{
  // Check if the file exists and is readable
  if (!file_exists($filePath) || !is_writable($filePath)) {
    // echo "Error: The file '$filePath' does not exist or cannot be read." . PHP_EOL;
    return;
  }

  // Open the file for reading with shared lock
  $inputFile = @fopen($filePath, 'r');
  if (!$inputFile) {
    // echo "Error: Unable to open file for reading: $filePath" . PHP_EOL;
    return;
  }

  // Attempt to acquire an exclusive lock on the file
  if (!flock($inputFile, LOCK_EX)) {
    // echo "Error: Unable to acquire exclusive lock on file: $filePath" . PHP_EOL;
    fclose($inputFile);
    return;
  }

  // Open a temporary file for writing
  $tempFile = tmpfile();
  if (!$tempFile) {
    // echo "Error: Unable to create temporary file." . PHP_EOL;
    fclose($inputFile);
    return;
  }

  // Read the input file line by line, remove empty lines, and write non-empty lines to the temporary file
  while (($line = fgets($inputFile)) !== false) {
    if (!empty(trim($line))) {
      fwrite($tempFile, $line);
    }
  }

  // Release the lock on the input file
  flock($inputFile, LOCK_UN);

  // Close both files
  fclose($inputFile);
  rewind($tempFile);

  // Rewrite the content of the input file with the content of the temporary file
  $outputFile = @fopen($filePath, 'w');
  if (!$outputFile) {
    // echo "Error: Unable to open file for writing: $filePath" . PHP_EOL;
    fclose($tempFile);
    return;
  }

  // Acquire an exclusive lock on the output file
  if (!flock($outputFile, LOCK_EX)) {
    // echo "Error: Unable to acquire exclusive lock on file: $filePath" . PHP_EOL;
    fclose($tempFile);
    fclose($outputFile);
    return;
  }

  // Copy content from temporary file to input file
  while (($line = fgets($tempFile)) !== false) {
    if (!empty(trim($line))) {
      fwrite($outputFile, $line);
    }
  }

  // Release the lock on the output file
  flock($outputFile, LOCK_UN);

  // Close the temporary file and the output file
  fclose($tempFile);
  fclose($outputFile);
}

/**
 * Moves the specified number of lines from one text file to another in append mode
 * and removes them from the source file.
 *
 * @param string $sourceFile Path to the source file.
 * @param string $destinationFile Path to the destination file.
 * @param int $linesToMove Number of lines to move.
 *
 * @return bool True if lines are moved and removed successfully, false otherwise.
 */
function moveLinesToFile(string $sourceFile, string $destinationFile, int $linesToMove): bool
{
  // Open the source file for reading and writing
  $sourceHandle = @fopen($sourceFile, 'r+');
  if (!$sourceHandle) {
    return false;
  }

  // Lock the source file
  flock($sourceHandle, LOCK_EX);

  // Open or create the destination file for appending
  $destinationHandle = @fopen($destinationFile, 'a');
  if (!$destinationHandle) {
    flock($sourceHandle, LOCK_UN);
    fclose($sourceHandle);
    return false;
  }

  // write new line
  fwrite($destinationHandle, PHP_EOL);

  // Read and write the specified number of lines
  for ($i = 0; $i < $linesToMove; $i++) {
    // Read a line from the source file
    $line = fgets($sourceHandle);
    if ($line === false) {
      // End of file reached
      break;
    }
    // Write the line to the destination file
    if (!empty(trim($line))) {
      fwrite($destinationHandle, $line);
    }
  }

  // Remove the moved lines from the source file
  $remainingContent = '';
  while (!feof($sourceHandle)) {
    $remainingContent .= fgets($sourceHandle);
  }
  ftruncate($sourceHandle, 0); // Clear the file
  rewind($sourceHandle);
  if (!empty(trim($remainingContent))) {
    fwrite($sourceHandle, $remainingContent);
  }

  // Close the file handles
  fclose($sourceHandle);
  fclose($destinationHandle);

  return true;
}

/**
 * Removes a specified string from a text file.
 *
 * @param string $file_path The path to the text file.
 * @param string|array $string_to_remove The string to remove from the file.
 * @return string Result message indicating success or failure.
 */
function removeStringFromFile(string $file_path, $string_to_remove): string
{
  if (is_file_locked($file_path)) {
    return "$file_path locked";
  }
  if (!is_writable($file_path)) {
    return "$file_path non-writable";
  }

  $content    = read_file($file_path);
  $is_content = is_string($content) && !empty(trim($content));
  if (!$content || !$is_content) {
    return "$file_path could not be read or has empty content";
  }

  $regex_pattern = string_to_regex($string_to_remove);
  if ($regex_pattern === null) {
    return "$string_to_remove invalid regex pattern";
  }

  $new_string = preg_replace($regex_pattern, PHP_EOL, $content, -1, $count);
  if ($new_string === null) {
    return 'removeStringFromFile: preg_replace failed';
  }
  if ($count === 0) {
    return 'removeStringFromFile: no string replaced';
  }

  $result = file_put_contents($file_path, $new_string);
  if ($result === false) {
    return 'removeStringFromFile: failed to write to file';
  }

  return 'success';
}

/**
 * Iterate over each line in a string, supporting LF, CRLF, and CR line endings.
 *
 * @param string $string The input string.
 * @param callable|bool $shuffle_or_callback If true, shuffle lines; if callable, callback function.
 * @param callable|null $callback The callback function to execute for each line.
 * @return void
 */
function iterateLines(string $string, $shuffle_or_callback, ?callable $callback = null): void
{
  // Normalize all newlines to LF (\n)
  $normalizedString = preg_replace('/\r?\n/', "\n", $string);

  // Split the string by LF
  $lines = explode("\n", $normalizedString);

  if (is_callable($shuffle_or_callback)) {
    $callback     = $shuffle_or_callback;
    $shuffleLines = false;
  } elseif ($shuffle_or_callback === true) {
    $shuffleLines = true;
  } else {
    $shuffleLines = false;
  }

  if ($shuffleLines) {
    shuffle($lines);
  }

  // Iterate over each line and execute the callback
  foreach ($lines as $index => $line) {
    if (trim($line) !== '') { // Skip empty lines
      if ($callback !== null) {
        $callback($line, $index);
      }
    }
  }
}

/**
 * Iterate over multiple big files line by line and execute a callback for each line.
 *
 * @param array $filePaths Array of file paths to iterate over.
 * @param callable|int $callbackOrMax Callback function or maximum number of lines to read.
 * @param callable|null $callback Callback function to execute for each line.
 */
function iterateBigFilesLineByLine(array $filePaths, $callbackOrMax = PHP_INT_MAX, ?callable $callback = null)
{
  foreach ($filePaths as $filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
      print("$filePath not found" . PHP_EOL);
      continue;
    }

    // Create a temporary file to copy the original file content
    $tempFile   = tmpfile();
    $sourceFile = @fopen($filePath, 'r');

    if ($sourceFile && $tempFile) {
      // Copy content from source file to temporary file
      while (($line = fgets($sourceFile)) !== false) {
        fwrite($tempFile, $line);
      }

      // Rewind the temporary file pointer
      rewind($tempFile);

      // Acquire an exclusive lock on the temporary file
      if (flock($tempFile, LOCK_SH)) {
        $maxLines  = is_callable($callbackOrMax) ? PHP_INT_MAX : $callbackOrMax;
        $linesRead = 0;

        while (($line = fgets($tempFile)) !== false && $linesRead < $maxLines) {
          // skip empty line
          if (empty(trim($line))) {
            continue;
          }
          // Execute callback for each line if $callbackOrMax is a callback
          if (is_callable($callbackOrMax)) {
            call_user_func($callbackOrMax, $line);
          } elseif (is_callable($callback)) {
            // Execute callback for each line if $callback is provided
            call_user_func($callback, $line);
          }

          $linesRead++;
        }

        // Release the lock and close the temporary file
        flock($tempFile, LOCK_UN);
        fclose($tempFile);
      } else {
        echo 'Failed to acquire lock for temporary file' . PHP_EOL;
      }

      fclose($sourceFile);
    } else {
      echo "Failed to open $filePath or create temporary file" . PHP_EOL;
    }
  }
}

/**
 * Remove duplicated lines from source file that exist in destination file.
 *
 * This function reads the contents of two text files line by line. If a line
 * from the source file exists in the destination file, it will be removed
 * from the source file.
 *
 * @param string $sourceFile Path to the source text file.
 * @param string $destinationFile Path to the destination text file.
 * @return bool True if operation is successful, false otherwise.
 */
function removeDuplicateLinesFromSource(string $sourceFile, string $destinationFile): bool
{
  // Open source file for reading
  $sourceHandle = @fopen($sourceFile, 'r');
  if (!$sourceHandle) {
    return false; // Unable to open source file
  }

  // Open destination file for reading
  $destinationHandle = @fopen($destinationFile, 'r');
  if (!$destinationHandle) {
    fclose($sourceHandle);
    return false; // Unable to open destination file
  }

  // Create a temporary file to store non-duplicated lines
  $tempFile = tmpfile();
  if (!$tempFile) {
    fclose($sourceHandle);
    fclose($destinationHandle);
    return false; // Unable to create temporary file
  }

  // Store destination lines in a hash set for faster lookup
  $destinationLines = [];
  while (($line = fgets($destinationHandle)) !== false) {
    $destinationLines[trim($line)] = true;
  }

  // Read lines from source file
  while (($line = fgets($sourceHandle)) !== false) {
    // Check if the line exists in the destination file
    if (!isset($destinationLines[trim($line)]) && !empty(trim($line))) {
      // If not, write the line to the temporary file
      fwrite($tempFile, $line);
    }
  }

  // Close file handles
  fclose($sourceHandle);
  fclose($destinationHandle);

  // Rewind the temporary file pointer
  rewind($tempFile);

  // Open source file for writing
  $sourceHandle = @fopen($sourceFile, 'w');
  if (!$sourceHandle) {
    fclose($tempFile);
    return false; // Unable to open source file for writing
  }

  // Copy contents from the temporary file to the source file
  while (!feof($tempFile)) {
    fwrite($sourceHandle, fgets($tempFile));
  }

  // Close file handles
  fclose($sourceHandle);
  fclose($tempFile);

  return true;
}

/**
 * Splits a large text file into multiple smaller files based on the maximum number of lines per file.
 *
 * @param string $largeFilePath The path to the large text file.
 * @param int $maxLinesPerFile The maximum number of lines per small file.
 * @param string $outputDirectory The directory where the small files will be stored.
 * @return void
 */
function splitLargeFile(string $largeFilePath, int $maxLinesPerFile, string $outputDirectory): void
{
  // Get the filename from the large file path
  $filename = pathinfo($largeFilePath, PATHINFO_FILENAME);

  // Open the large file for reading
  $handle = @fopen($largeFilePath, 'r');

  // Counter for the lines read
  $lineCount = 0;

  // Counter for the small file index
  $fileIndex = 1;

  // Create the first small file
  $smallFile = @fopen($outputDirectory . '/' . $filename . '_part_' . $fileIndex . '.txt', 'w');

  // Loop through the large file line by line
  while (!feof($handle)) {
    $line = fgets($handle);

    // Write the line to the current small file
    if (!empty(trim($line))) {
      fwrite($smallFile, $line);
    }

    // Increment the line count
    $lineCount++;

    // Check if reached maximum lines per small file
    if ($lineCount >= $maxLinesPerFile) {
      // Close the current small file
      fclose($smallFile);

      // Increment the file index
      $fileIndex++;

      // Open a new small file
      $smallFile = @fopen($outputDirectory . '/' . $filename . '_part_' . $fileIndex . '.txt', 'w');

      // Reset the line count
      $lineCount = 0;
    }
  }

  // Close the handle to the large file
  fclose($handle);

  // Close the last small file
  fclose($smallFile);

  echo 'Splitting complete!';
}

/**
 * Get duplicated lines between two text files.
 *
 * This function reads the contents of two text files line by line and
 * returns an array containing the duplicated lines found in both files.
 *
 * @param string $file1 Path to the first text file.
 * @param string $file2 Path to the second text file.
 * @return array An array containing the duplicated lines between the two files.
 */
function getDuplicatedLines(string $file1, string $file2): array
{
  // Open files for reading
  $handle1 = @fopen($file1, 'r');
  $handle2 = @fopen($file2, 'r');

  // Initialize arrays to store lines
  $lines1 = [];
  $lines2 = [];

  // Read lines from first file
  while (($line = fgets($handle1)) !== false) {
    $lines1[] = $line;
  }

  // Read lines from second file
  while (($line = fgets($handle2)) !== false) {
    $lines2[] = $line;
  }

  // Close file handles
  fclose($handle1);
  fclose($handle2);

  // Find duplicated lines
  // Return array of duplicated lines
  return array_intersect($lines1, $lines2);
}

/**
 * Get a random file from a folder.
 *
 * @param string $folder The path to the folder containing files.
 * @param string|null $file_extension The optional file extension without dot (.) to filter files by.
 * @return string|null The name of the randomly selected file, or null if no file found with the specified extension.
 */
function getRandomFileFromFolder(string $folder, string $file_extension = null): ?string
{
  if (!$folder || !file_exists($folder)) {
    return null;
  }
  // Get list of files in the folder
  $files = scandir($folder);
  if (!$files) {
    return null;
  }

  // Remove special directories "." and ".." from the list
  $files = array_diff($files, ['.', '..']);

  // Re-index the array
  $files = array_values($files);

  // Filter files by extension if provided
  if ($file_extension !== null) {
    $files = array_filter($files, function ($file) use ($file_extension) {
      return pathinfo($file, PATHINFO_EXTENSION) == $file_extension;
    });
  }

  // Get number of files
  $num_files = count($files);

  // Check if there are files with the specified extension
  if ($num_files === 0) {
    return null; // No files found with the specified extension
  }

  // Generate a random index
  $random_index = mt_rand(0, $num_files - 1);

  // Get the randomly selected file
  $random_file = $files[$random_index];

  return $folder . '/' . $random_file;
}

/**
 * Filters unique lines in a large file and overwrites the input file with the filtered lines.
 *
 * This function reads the input file line by line, removes duplicate lines,
 * and overwrites the input file with the filtered unique lines.
 *
 * @param string $inputFile The path to the input file.
 * @return void
 */
function filterUniqueLines(string $inputFile)
{
  // Open input file for reading and writing
  $inputHandle = @fopen($inputFile, 'r+');

  // Create a temporary file for storing unique lines
  $tempFile = tmpfile();

  // Copy unique lines to the temporary file
  while (($line = fgets($inputHandle)) !== false) {
    $line = trim($line);
    if (!empty($line)) {
      // Check if line is unique
      if (strpos(stream_get_contents($tempFile), $line) === false) {
        if (!empty(trim($line))) {
          fwrite($tempFile, $line . PHP_EOL);
        }
      }
    }
  }

  // Rewind both file pointers
  rewind($inputHandle);
  rewind($tempFile);

  // Clear the contents of the input file
  ftruncate($inputHandle, 0);

  // Copy the unique lines back to the input file
  stream_copy_to_stream($tempFile, $inputHandle);

  // Close file handles
  fclose($inputHandle);
  fclose($tempFile);
}

/**
 * Function to truncate the content of a file
 */
function truncateFile(string $filePath)
{
  // Write an empty string to truncate the file
  write_file($filePath, '');
}

/**
 * Move content from a source file to a destination file in append mode.
 *
 * @param string $sourceFile The path to the source file.
 * @param string $destinationFile The path to the destination file.
 *
 * @return string An error message if there's an issue, otherwise "success" for success.
 */
function moveContent(string $sourceFile, string $destinationFile): string
{
  // Check if source file is readable
  if (!is_readable($sourceFile)) {
    return "$sourceFile not readable";
  }

  // Check if destination file is writable
  if (!is_writable($destinationFile)) {
    return "$destinationFile not writable";
  }

  // Check if source file is locked
  if (is_file_locked($sourceFile)) {
    return "$sourceFile locked";
  }

  // Check if destination file is locked
  if (is_file_locked($destinationFile)) {
    return "$destinationFile locked";
  }

  // Open the source file for reading
  $sourceHandle = @fopen($sourceFile, 'r');

  // Open the destination file for appending
  $destinationHandle = @fopen($destinationFile, 'a');

  // Attempt to acquire locks on both files
  $lockSource      = $sourceHandle      && flock($sourceHandle, LOCK_SH);
  $lockDestination = $destinationHandle && flock($destinationHandle, LOCK_EX);

  // Check if both files are opened and locked successfully
  if ($sourceHandle && $destinationHandle && $lockSource && $lockDestination) {
    // Read content from the source file and write it to the destination file
    while (($line = fgets($sourceHandle)) !== false) {
      fwrite($destinationHandle, $line);
    }

    // Close both files and release locks
    flock($sourceHandle, LOCK_UN);
    flock($destinationHandle, LOCK_UN);
    fclose($sourceHandle);
    fclose($destinationHandle);

    return 'success'; // Success, so return "success"
  } else {
    // Close both files if they were opened
    if ($sourceHandle) {
      fclose($sourceHandle);
    }
    if ($destinationHandle) {
      fclose($destinationHandle);
    }

    return 'Failed to move content'; // Indicate failure
  }
}

/**
 * Remove duplicate lines from a file in-place.
 *
 * @param string $inputFile The path to the file.
 * @return void
 */
function removeDuplicateLines(string $inputFile): void
{
  if (!file_exists($inputFile)) {
    echo "removeDuplicateLines: $inputFile is not found" . PHP_EOL;
    return;
  }
  if (is_file_locked($inputFile)) {
    echo "removeDuplicateLines: $inputFile is locked" . PHP_EOL;
    return;
  }
  if (!is_writable($inputFile)) {
    echo "removeDuplicateLines: $inputFile is not writable" . PHP_EOL;
    return;
  }
  $lines = [];
  $fd    = @fopen($inputFile, 'r');
  if ($fd === false) {
    echo "removeDuplicateLines: Failed to open $inputFile" . PHP_EOL;
    return;
  }
  if (flock($fd, LOCK_EX)) { // Acquire an exclusive lock
    while ($line = fgets($fd)) {
      $line         = rtrim($line, "\r\n"); // ignore the newline
      $lines[$line] = 1;
    }
    flock($fd, LOCK_UN); // Release the lock
  }
  fclose($fd);
  $fd = @fopen($inputFile, 'w');
  if ($fd === false) {
    echo "removeDuplicateLines: Failed to open $inputFile" . PHP_EOL;
    return;
  }
  if (flock($fd, LOCK_EX)) { // Acquire an exclusive lock
    foreach ($lines as $line => $count) {
      fputs($fd, "$line" . PHP_EOL); // add the newlines back
    }
    flock($fd, LOCK_UN); // Release the lock
  }
  fclose($fd);
}

/**
 * Count the number of non-empty lines in a file.
 *
 * This function reads the specified file line by line and counts the number
 * of non-empty lines.
 *
 * @param string $filePath The path to the file.
 * @param int $chunkSize Optional. The size of each chunk to read in bytes. Defaults to 4096.
 * @return int|false The number of non-empty lines in the file, or false if the file couldn't be opened.
 */
function countNonEmptyLines(string $filePath, int $chunkSize = 4096)
{
  if (!file_exists($filePath)) {
    return 0;
  }
  if (!is_readable($filePath)) {
    return 0;
  }
  if (is_file_locked($filePath)) {
    return 0;
  }
  $file = @fopen($filePath, 'r');
  if (!$file) {
    return false; // File open failed
  }

  $count  = 0;
  $buffer = '';

  while (!feof($file)) {
    $buffer .= fread($file, $chunkSize);
    $lines = explode("\n", $buffer);
    $count += count($lines) - 1;
    $buffer = array_pop($lines);
  }

  fclose($file);

  // Add 1 for the last line if it's not empty
  if (trim($buffer) !== '') {
    $count++;
  }

  return $count;
}

/**
 * Remove lines from a string or file with a length less than a specified minimum length.
 *
 * @param string $inputStringOrFilePath The input string or file path.
 * @param int $minLength The minimum length for lines to be retained.
 * @return string The resulting string with lines removed.
 */
function removeShortLines(string $inputStringOrFilePath, int $minLength): string
{
  if (is_file($inputStringOrFilePath)) {
    // If the input is a file, read its contents
    $inputString = read_file($inputStringOrFilePath);
  } else {
    // If the input is a string, use it directly
    $inputString = $inputStringOrFilePath;
  }

  // Split the string into an array of lines
  $lines = explode("\n", $inputString);

  // Filter out lines with less than the minimum length
  $filteredLines = array_filter($lines, function ($line) use ($minLength) {
    return strlen($line) >= $minLength;
  });

  // Join the filtered lines back into a string
  return implode("\n", $filteredLines);
}

/**
 * Read the first N non-empty lines from a file.
 *
 * @param string $filePath The path to the file.
 * @param int $lines_to_read The number of lines to read.
 * @return array|false An array containing the first N non-empty lines from the file, or false on failure.
 */
function read_first_lines(string $filePath, int $lines_to_read)
{
  if (is_file_locked($filePath)) {
    return false;
  }
  if (!is_writable($filePath)) {
    return false;
  }
  if (!file_exists($filePath)) {
    return false;
  }
  $lines  = [];
  $handle = @fopen($filePath, 'r');
  if (!$handle) {
    // Handle error opening the file
    return false;
  }

  // Obtain a lock on the file
  flock($handle, LOCK_SH);

  $count = 0;
  while (($line = fgets($handle)) !== false && $count < $lines_to_read) {
    // Skip empty lines
    if (!empty(trim($line))) {
      $lines[] = $line;
      $count++;
    }
  }

  // Release the lock and close the file
  flock($handle, LOCK_UN);
  fclose($handle);

  return $lines;
}


/** Ensure directory exists helper (keeps behavior but avoids repeating mkdir checks) */
function ensure_dir(string $dir): void
{
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
  }
}

/** Small helper to safely unlink a file if it exists */
function safe_unlink(string $file): void
{
  if (file_exists($file)) {
    @unlink($file);
  }
}
