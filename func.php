<?php

define('PHP_PROXY_HUNTER_PROJECT_ROOT', __DIR__);

require_once __DIR__ . '/vendor/symfony/polyfill-mbstring/bootstrap.php';
include __DIR__ . '/src/utils/shim/string.php';
include __DIR__ . '/src/database/env.php';

$isCli = is_cli();

define('PHP_PROXY_HUNTER', 'true');

if (!defined('JSON_THROW_ON_ERROR')) {
  define('JSON_THROW_ON_ERROR', 4194304);
}

define('PROJECT_ROOT', __DIR__);

// Detect if the system is Windows
$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

// Get the current PATH
$currentPath = getenv('PATH');

if ($currentPath === false) {
  // Set a default PATH value (customize as needed)
  $defaultPath = $isWin ? 'C:\Windows\System32' : '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
  $currentPath = $defaultPath;
  // var_dump("Setting default PATH", $currentPath);
}

if ($currentPath) {
  // var_dump("original PATH", $currentPath);

  // Specify the new directories to add
  $paths         = [__DIR__ . '/venv/Scripts'];
  $PathSeparator = $isWin ? ';' : ':';
  $newDirectory  = implode($PathSeparator, $paths);

  // Combine the current PATH with the new directory
  $newPath     = $newDirectory . $PathSeparator . $currentPath;
  $explodePath = array_filter(array_unique(explode($PathSeparator, $newPath)));
  $explodePath = array_map(function ($str) {
    // Only apply realpath if the directory exists
    $realPath = realpath($str);
    if ($realPath === false) {
      return $str;
    }
    return $realPath;
  }, $explodePath);

  // Additional logging for debugging
  // var_dump("Directories before filtering", $explodePath);

  $newPath = implode($PathSeparator, $explodePath);

  // Add the new directory to the PATH
  putenv("PATH=$newPath");

  // Verify the change
  // $updatedPath = getenv('PATH');
  // var_dump("modified PATH", $updatedPath);

  // $output = shell_exec("echo %PATH%");
  // echo $output;

  // exit;
}

// Detect admin
$isAdmin = is_debug();
if (!$isAdmin) {
  if ($isCli) {
    // CLI
    $short_opts = 'p::m::';
    $long_opts  = [
      'proxy::',
      'max::',
      'userId::',
      'lockFile::',
      'runner::',
      'admin::',
    ];
    $options = getopt($short_opts, $long_opts);
    $isAdmin = !empty($options['admin']) && $options['admin'] !== 'false';
  }
}

// ===== START error reporting settings =====

// debug all errors
$enable_debug = $isAdmin;
if ($enable_debug) {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
} else {
  ini_set('display_errors', 0);
  ini_set('display_startup_errors', 0);
}
ini_set('log_errors', 1);
// Enable error logging
$error_file = __DIR__ . '/tmp/logs/php-error.txt';
if (!$isCli) {
  // Sanitize the user agent string
  $user_agent = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

  // Check if the sanitized user agent is empty
  if (empty($user_agent)) {
    $error_file = __DIR__ . '/tmp/logs/php-error.txt';
  } else {
    $error_file = __DIR__ . '/tmp/logs/php-error-' . $user_agent . '.txt';
  }
}
ini_set('error_log', $error_file);
// set error path

if ($enable_debug) {
  error_reporting(E_ALL);
  if (!is_debug_device() && version_compare(PHP_VERSION, '8.4', '>=')) {
    // Suppress PHP 8.4 deprecation notices about implicit nullable params on production
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
  }
} else {
  error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
}

// ===== END error reporting settings =====

// set default timezone
date_default_timezone_set('Asia/Jakarta');

// allocate memory
ini_set('memory_limit', '128M');

// ignore limitation if exists
if (function_exists('set_time_limit')) {
  // Disables the time limit completely
  call_user_func('set_time_limit', 0);
}

require_once __DIR__ . '/vendor/autoload.php';

use PhpProxyHunter\Session;

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// keep running when user closed the connection (true)
// ignore_user_abort(true);
// ignore user abort execution to false
if (function_exists('ignore_user_abort')) {
  call_user_func('ignore_user_abort', false);
}

// Start session for web server
if (!$isCli) {
  /** @noinspection PhpUnhandledExceptionInspection */
  new Session(100 * 3600, __DIR__ . '/tmp/sessions');
  // web server admin
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

// Define $argv for web server context
$argv = isset($argv) ? $argv : [];

/**
 * Returns an array of unique objects from the provided array based on a specific property.
 *
 * @param array $array An array of objects
 * @param string $property The property name to compare
 * @return array An array of unique objects
 */
function uniqueClassObjectsByProperty(array $array, string $property): array {
  $tempArray = [];
  $result    = [];
  foreach ($array as $item) {
    if (property_exists($item, $property)) {
      $value = $item->$property;
      if (!isset($tempArray[$value])) {
        $tempArray[$value] = true;
        $result[]          = $item;
      }
    }
  }
  return $result;
}

/**
 * Checks if a given string is base64 encoded.
 *
 * @param string|null $string The string to check.
 * @return bool True if the string is base64 encoded, false otherwise.
 */
function isBase64Encoded($string): bool {
  if (empty($string)) {
    return false;
  }
  // Check if the string matches the base64 format
  if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string)) {
    // Decode the string and then re-encode it
    $decoded = base64_decode($string, true);
    if ($decoded !== false) {
      // Compare the re-encoded string to the original
      if (base64_encode($decoded) === $string) {
        return true;
      }
    }
  }
  return false;
}

/**
 * Remove specified string from source file and move it to destination file.
 *
 * This function reads the source file line by line, removes the specified string,
 * and writes the modified content back to the source file. It also appends the removed
 * string to the destination file.
 *
 * @param string $sourceFilePath Path to the source file.
 * @param string $destinationFilePath Path to the destination file.
 * @param string|null $stringToRemove The string to remove from the source file.
 * @return string Message indicating success or failure.
 */
function removeStringAndMoveToFile(string $sourceFilePath, string $destinationFilePath, $stringToRemove): string {
  if (!file_exists($destinationFilePath)) {
    file_put_contents($destinationFilePath, '');
  }
  // Check if $stringToRemove is empty or contains only whitespace characters
  if (is_null($stringToRemove) || empty(trim($stringToRemove))) {
    return 'Empty string to remove';
  }

  // Check if source file is writable
  if (!is_writable($sourceFilePath)) {
    return "$sourceFilePath not writable";
  }

  // Check if destination file is writable
  if (!is_writable($destinationFilePath)) {
    return "$destinationFilePath not writable";
  }

  // Check if source file is locked
  if (is_file_locked($sourceFilePath)) {
    return "$sourceFilePath locked";
  }

  // Check if destination file is locked
  if (is_file_locked($destinationFilePath)) {
    return "$destinationFilePath locked";
  }

  // Open source file for reading
  $sourceHandle = @fopen($sourceFilePath, 'r');
  if (!$sourceHandle) {
    return 'Failed to open source file';
  }

  // Open destination file for appending
  $destinationHandle = @fopen($destinationFilePath, 'a');
  if (!$destinationHandle) {
    fclose($sourceHandle);
    return 'Failed to open destination file';
  }

  // Open a temporary file for writing
  $tempFilePath = tempnam(sys_get_temp_dir(), 'source_temp');
  $tempHandle   = @fopen($tempFilePath, 'w');
  if (!$tempHandle) {
    fclose($sourceHandle);
    fclose($destinationHandle);
    return 'Failed to create temporary file';
  }

  // Acquire an exclusive lock on the source file
  if (flock($sourceHandle, LOCK_SH)) {
    // Iterate through each line in the source file
    while (($line = fgets($sourceHandle)) !== false) {
      // Remove the string from the current line
      $modifiedLine = str_replace($stringToRemove, '', $line);
      // Write the modified line to the temporary file
      fwrite($tempHandle, $modifiedLine);
    }

    // Close file handles
    flock($sourceHandle, LOCK_UN);
    fclose($sourceHandle);
    fclose($tempHandle);
    fclose($destinationHandle);

    // Replace the source file with the temporary file
    if (!rename($tempFilePath, $sourceFilePath)) {
      unlink($tempFilePath);
      return 'Failed to replace source file';
    }

    // Append the removed string to the destination file
    if (file_put_contents($destinationFilePath, PHP_EOL . $stringToRemove . PHP_EOL, FILE_APPEND) === false) {
      return 'Failed to append removed string to destination file';
    }

    return 'Success';
  } else {
    fclose($sourceHandle);
    fclose($destinationHandle);
    fclose($tempHandle);
    unlink($tempFilePath);
    return 'Failed to acquire lock on source file';
  }
}

/**
 * get cache file from `curlGetWithProxy`
 */
function curlGetCache($url): string {
  return __DIR__ . '/.cache/' . md5($url);
}

/**
 * Fetches the content of a URL using cURL with a specified proxy, with caching support.
 *
 * @param string $url The URL to fetch.
 * @param string|null $proxy The proxy IP address and port (e.g., "proxy_ip:proxy_port").
 * @param string|null $proxyType The type of proxy. Can be 'http', 'socks4', or 'socks5'. Defaults to 'http'.
 * @param float|int $cacheTime The cache expiration time in seconds. Set to 0 to disable caching. Defaults to 1 year (86400 * 360 seconds).
 * @param string $cacheDir The directory where cached responses will be stored. Defaults to './.cache/' in the current directory.
 * @param string|null $username Optional proxy username to use for proxy authentication.
 * @param string|null $password Optional proxy password to use for proxy authentication.
 * @return string|false The response content or false on failure.
 */
function curlGetWithProxy(string $url, $proxy = null, $proxyType = 'http', $cacheTime = 86400 * 360, string $cacheDir = __DIR__ . '/.cache/', $username = null, $password = null) {
  // Generate cache file path based on URL
  if (!file_exists($cacheDir)) {
    mkdir($cacheDir);
  }
  $cacheFile = $cacheDir . md5($url);

  // Check if cached data exists and is still valid
  if ($cacheTime > 0 && file_exists($cacheFile) && time() - filemtime($cacheFile) < $cacheTime) {
    // Return cached response
    return read_file($cacheFile);
  }

  // Initialize cURL session (forward optional proxy credentials to buildCurl)
  // buildCurl signature: buildCurl($proxy, $type, $endpoint, $headers = [], $username = null, $password = null, ...)
  $ch = buildCurl($proxy, $proxyType, $url, [], $username, $password);
  if ($ch) {
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  }

  // Execute the request
  $response = curl_exec($ch);

  // Check for errors
  if (curl_errno($ch)) {
    // echo 'Error: ' . curl_error($ch);
  } else {
    // Save response to cache file
    if ($cacheTime > 0) {
      write_file($cacheFile, $response);
    }
  }

  // Close cURL session
  curl_close($ch);

  // Return the response
  return $response;
}

/**
 * Get all files with a specific extension in a folder.
 *
 * @param string $folder The folder path to search for files.
 * @param string|null $extension The file extension to filter files by.
 *
 * @return array An array containing full paths of files with the specified extension.
 */
function getFilesByExtension(string $folder, $extension = 'txt'): array {
  if (!file_exists($folder)) {
    echo "$folder not exist" . PHP_EOL;
    return [];
  }

  $files  = [];
  $folder = rtrim($folder, '/') . '/';
  // Ensure folder path ends with a slash

  // Open the directory
  if ($handle = opendir($folder)) {
    // Loop through directory contents
    while (false !== ($entry = readdir($handle))) {
      $file = $folder . $entry;

      // ensure it's a file
      if ($entry != '.' && $entry != '..' && is_file($file)) {
        if (!empty($extension)) {
          // Check if file has the specified extension
          if (pathinfo($file, PATHINFO_EXTENSION) === $extension) {
            $files[] = realpath($file);
          }
        }
      }
    }
    closedir($handle);
  } else {
    echo "cannot open $folder\n";
  }

  return $files;
}

/**
 * Merges two shallow multidimensional arrays.
 *
 * This function merges two multidimensional arrays while preserving the structure.
 * If a key exists in both arrays, sub-arrays are merged recursively.
 * Values from the second array override those from the first array if they have the same keys.
 * If a key exists in the second array but not in the first one, it will be added to the merged array.
 *
 * @param array $arr1 The first array to merge.
 * @param array $arr2 The second array to merge.
 * @return array The merged array.
 */
function mergeArrays(array $arr1, array $arr2): array {
  $keys = array_keys($arr2);
  foreach ($keys as $key) {
    if (isset($arr1[$key]) && is_numeric($key)) {
      array_push($arr1, $arr2[$key]);
    } elseif (isset($arr1[$key]) && is_array($arr1[$key]) && is_array($arr2[$key])) {
      $arr1[$key] = array_unique(mergeArrays((array)$arr1[$key], (array)$arr2[$key]));
    } else {
      $arr1[$key] = $arr2[$key];
    }
  }
  return $arr1;
}

/**
 * Iterate over the array, limiting the number of iterations to the specified limit.
 *
 * If the length of the array is greater than the limit, only the first `$limit` items will be iterated over.
 *
 * @param array $array The array to iterate over.
 * @param int $limit The maximum number of items to iterate over. Default is 50.
 * @param callable|null $callback A callback function to be called for each item during iteration.
 * @return void
 */
function iterateArray(array $array, int $limit = 50, $callback = null) {
  $arrayLength = count($array);
  $limit       = min($arrayLength, $limit);
  // Get the minimum of array length and $limit
  for ($i = 0; $i < $limit; $i++) {
    // Access array element at index $i and perform desired operations
    $item = $array[$i];
    if ($callback !== null && is_callable($callback)) {
      call_user_func($callback, $item);
    } else {
      echo $item . "\n";
    }
  }
}

/**
 * Fixes a text file containing NUL characters by removing them.
 *
 * @param string $inputFile The path to the input file.
 * @return void
 */
function fixFile(string $inputFile) {
  if (!file_exists($inputFile)) {
    echo "fixFile: $inputFile is not found" . PHP_EOL;
    return;
  }
  if (is_file_locked($inputFile)) {
    echo "fixFile: $inputFile is locked" . PHP_EOL;
    return;
  }
  if (!is_writable($inputFile)) {
    echo "fixFile: $inputFile is not writable" . PHP_EOL;
    return;
  }

  // Open the file for reading and writing (binary mode)
  $fileHandle = @fopen($inputFile, 'r+');

  // Attempt to acquire an exclusive lock on the file
  if (flock($fileHandle, LOCK_EX)) {
    // Iterate through the file and remove NUL characters
    while (!feof($fileHandle)) {
      // Read a chunk of data
      $chunk = fread($fileHandle, 1024 * 1024);
      // Read in 500KB chunks
      if (!$chunk) {
        continue;
      }

      // Remove NUL characters directly from the chunk
      $cleanedChunk = str_replace("\x00", '', $chunk);

      // Rewind to the current position
      fseek($fileHandle, -strlen($chunk), SEEK_CUR);

      // Write the cleaned chunk back to the file
      fwrite($fileHandle, $cleanedChunk);
    }

    // Truncate the file to remove any extra content after cleaning
    ftruncate($fileHandle, ftell($fileHandle));

    // Release the lock
    flock($fileHandle, LOCK_UN);

    // Close the file handle
    fclose($fileHandle);
  } else {
    echo 'fixFile: Unable to acquire lock.' . PHP_EOL;
  }
}

function isCygwinInstalled() {
  $cygwinExecutables = ['bash', 'ls'];
  // List of Cygwin executables to check

  foreach ($cygwinExecutables as $executable) {
    $output = shell_exec("where $executable 2>&1");
    if (strpos($output, 'not found') === false) {
      return true;
      // Found at least one Cygwin executable
    }
  }

  return false;
  // None of the Cygwin executables found
}

function runShellCommandLive($command) {
  if (!is_string($command)) {
    throw new InvalidArgumentException('Command must be a string');
  }

  // Open a process for the command
  $process = proc_open($command, [
    1 => ['pipe', 'w'], // stdout
    2 => ['pipe', 'w'],  // stderr
  ], $pipes);

  if (is_resource($process)) {
    // Get the stdout pipe
    $stdout = $pipes[1];
    $stderr = $pipes[2];

    // Close stderr pipe
    fclose($stderr);

    // Read stdout line by line
    while (($line = fgets($stdout)) !== false) {
      echo htmlspecialchars($line) . "\n";
      flush();
      // Make sure the output is sent to the browser immediately
    }

    fclose($stdout);

    // Wait for the process to finish
    $return_value = proc_close($process);

    // Check for errors if necessary
    if ($return_value !== 0) {
      echo "Command failed with return code $return_value";
    }
  } else {
    throw new RuntimeException('Failed to open process');
  }
}

function safe_json_decode($json, $assoc = true) {
  $decoded = json_decode($json, $assoc);

  // Check for JSON decode errors
  if (json_last_error() !== JSON_ERROR_NONE) {
    // Return null if there's an error
    return null;
  }

  return $decoded;
}

/**
 * Removes specified keys from a multi-dimensional array.
 *
 * This function recursively iterates over the array and removes all occurrences
 * of the specified keys at any level of the array.
 *
 * @param array $array The array from which keys should be removed. Passed by reference.
 * @param array $keysToRemove An array of keys to remove from the array.
 *
 * @return void
 */
function remove_array_keys(array &$array, array $keysToRemove) {
  foreach ($array as $key => &$value) {
    if (is_array($value)) {
      // Recursively apply the function to nested arrays
      remove_array_keys($value, $keysToRemove);
    }

    // If the key is in the list of keys to remove, unset it
    if (in_array($key, $keysToRemove, true)) {
      unset($array[$key]);
    }
  }
}
