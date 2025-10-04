<?php

/** @noinspection PhpDefineCanBeReplacedWithConstInspection */
/** @noinspection RegExpRedundantEscape */

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

// debug all errors
$enable_debug = $isAdmin;
if ($enable_debug) {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
} else {
  ini_set('display_errors', 0);
  ini_set('display_startup_errors', 0);
}
ini_set('log_errors', 1); // Enable error logging
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
ini_set('error_log', $error_file); // set error path

if ($enable_debug) {
  error_reporting(E_ALL);
} else {
  error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
}

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
function uniqueClassObjectsByProperty(array $array, string $property): array
{
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
 * Create parent folders for a file path if they don't exist.
 *
 * @param string $filePath The file path for which to create parent folders.
 *
 * @return bool True if all parent folders were created successfully or already exist, false otherwise.
 */
function createParentFolders(string $filePath): bool
{
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

/**
 * Sets file permissions to 777 if the file exists.
 *
 * @param string|array $filenames The filename(s) to set permissions for.
 *                                Can be a single filename or an array of filenames.
 * @param bool $autoCreate (optional) Whether to automatically create the file if it doesn't exist. Default is false.
 *
 * @return void
 */
function setMultiPermissions($filenames, bool $autoCreate = false)
{
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
function setPermissions(string $filename, bool $autoCreate = false): bool
{
  try {
    if (!file_exists($filename) && $autoCreate) {
      write_file($filename, '');
    }
    if (file_exists($filename) && is_readable($filename) && is_writable($filename)) {
      return chmod($filename, 0777);
    }
  } catch (Throwable $th) {
    return false;
  }
  return false;
}

/**
 * Checks if a given string is base64 encoded.
 *
 * @param string|null $string The string to check.
 * @return bool True if the string is base64 encoded, false otherwise.
 */
function isBase64Encoded(?string $string): bool
{
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
function removeStringAndMoveToFile(string $sourceFilePath, string $destinationFilePath, ?string $stringToRemove): string
{
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
function curlGetCache($url): string
{
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
 * @return string|false The response content or false on failure.
 */
function curlGetWithProxy(string $url, ?string $proxy = null, ?string $proxyType = 'http', $cacheTime = 86400 * 360, string $cacheDir = __DIR__ . '/.cache/')
{
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

  // Initialize cURL session
  $ch = buildCurl($proxy, $proxyType, $url);
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
 * Function to extract IP:PORT combinations from a text file and rewrite the file with only IP:PORT combinations.
 *
 * @param string $filename The path to the text file.
 * @return bool True on success, false on failure.
 */
function rewriteIpPortFile(string $filename): bool
{
  if (!file_exists($filename) || !is_readable($filename) || !is_writable($filename)) {
    echo "File '$filename' is not readable or writable" . PHP_EOL;
    return false;
  }

  // Open the file for reading
  $file = @fopen($filename, 'r');
  if (!$file) {
    echo "Error opening $filename for reading" . PHP_EOL;
    return false;
  }

  // Open a temporary file for writing
  $tempFilename = tempnam(__DIR__ . '/tmp', 'rewriteIpPortFile');
  $tempFile     = @fopen($tempFilename, 'w');
  if (!$tempFile) {
    fclose($file); // Close the original file
    echo "Error opening temporary ($tempFilename) file for writing";
    return false;
  }

  // Read each line from the file and extract IP:PORT combinations
  while (($line = fgets($file)) !== false) {
    // Match IP:PORT pattern using regular expression
    preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+)/', $line, $matches);

    // Write matched IP:PORT combinations to the temporary file
    foreach ($matches[0] as $match) {
      if (!empty(trim($match))) {
        fwrite($tempFile, $match . "\n");
      }
    }
  }

  // Close both files
  fclose($file);
  fclose($tempFile);

  // Replace the original file with the temporary file
  if (!rename($tempFilename, $filename)) {
    echo 'Error replacing original file with temporary file';
    return false;
  }

  return true;
}

/**
 * Reads a file line by line and returns its content as an array.
 *
 * @param string $filename The path to the file to be read.
 * @return array|false An array containing the lines of the file on success, false on failure.
 */
function readFileLinesToArray(string $filename)
{
  // Check if the file exists and is readable
  if (!is_readable($filename)) {
    return false;
  }

  $lines = [];

  // Open the file for reading
  $file = @fopen($filename, 'r');

  // Read each line until the end of the file
  while (!feof($file)) {
    // Read the line
    $line = fgets($file);
    // Add the line to the array
    $lines[] = $line;
  }

  // Close the file
  fclose($file);

  return $lines;
}

/**
 * Get all files with a specific extension in a folder.
 *
 * @param string $folder The folder path to search for files.
 * @param string|null $extension The file extension to filter files by.
 *
 * @return array An array containing full paths of files with the specified extension.
 */
function getFilesByExtension(string $folder, ?string $extension = 'txt'): array
{
  if (!file_exists($folder)) {
    echo "$folder not exist" . PHP_EOL;
    return [];
  }

  $files  = [];
  $folder = rtrim($folder, '/') . '/'; // Ensure folder path ends with a slash

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
 * Check if a given date string in RFC3339 format is older than the specified number of hours.
 *
 * @param string $dateString The date string in DATE_RFC3339 format.
 * @param int $hoursAgo The number of hours to compare against.
 * @return bool True if the date is older than the specified number of hours, false otherwise.
 */
function isDateRFC3339OlderThanHours(string $dateString, int $hoursAgo = 5): bool
{
  try {
    // Create a DateTime object from the string
    $date = new DateTime($dateString);
  } catch (Exception $e) {
    // Handle exception if DateTime creation fails
    return false;
  }

  try {
    // Create a DateTime object representing the specified number of hours ago
    $hoursAgoDateTime = new DateTime();
    $hoursAgoDateTime->sub(new DateInterval('PT' . $hoursAgo . 'H'));
  } catch (Exception $e) {
    // Handle exception if DateTime creation fails
    return false;
  }

  // Compare the date with the specified number of hours ago
  return $date < $hoursAgoDateTime;
}

// Function to parse command line arguments
function parseArgs($args): array
{
  $parsedArgs = [];

  foreach ($args as $arg) {
    if (substr($arg, 0, 2) === '--') {
      // Argument is in the format --key=value
      $parts            = explode('=', substr($arg, 2), 2);
      $key              = $parts[0];
      $value            = $parts[1] ?? true; // If value is not provided, set it to true
      $parsedArgs[$key] = $value;
    }
  }

  return $parsedArgs;
}

// Default user ID to "CLI" assuming the script is running from the command line
$user_id = 'CLI';

if (!$isCli) {
  // If not running in CLI mode, generate a hashed user ID
  // Use the session user ID if available; otherwise, fall back to the session ID
  $user_id = md5($_SESSION['user_id'] ?? session_id());
} elseif (!empty($parsedArgs = parseArgs($argv)) && !empty(trim($parsedArgs['userId'] ?? ''))) {
  // If running in CLI mode and 'userId' is provided in arguments
  // Trim the value to remove any surrounding whitespace
  $user_id = trim($parsedArgs['userId']);
}

setUserId($user_id);

/**
 * Set the current user ID and create a user config file if it doesn't exist.
 *
 * @param string $new_user_id The new user ID to be set.
 *
 * This function sets the global user ID, ensures the user config file exists,
 * and writes default config if the file is not already present.
 */
function setUserId(string $new_user_id)
{
  global $user_id;
  $user_file = !empty($new_user_id) ? getUserFile($new_user_id) : null;

  if ($user_file != null) {
    // Ensure the directory for the user file exists
    if (!file_exists(dirname($user_file))) {
      mkdir(dirname($user_file), 0777, true);
    }

    // Write default user config if file does not exist
    if (!file_exists($user_file)) {
      $headers = [
        'X-Dynatrace: MT_3_6_2809532683_30-0_24d94a15-af8c-49e7-96a0-1ddb48909564_0_1_619',
        'X-Api-Key: vT8tINqHaOxXbGE7eOWAhA==',
        'Authorization: Bearer',
        'X-Request-Id: 63337f4c-ec03-4eb8-8caa-4b7cd66337e3',
        'X-Request-At: 2024-04-07T20:57:14.73+07:00',
        'X-Version-App: 5.8.8',
        'User-Agent: myXL / 5.8.8(741); StandAloneInstall; (samsung; SM-G955N; SDK 25; Android 7.1.2)',
        'Content-Type: application/json; charset=utf-8',
      ];

      $data = [
        'endpoint' => $new_user_id == 'CLI'
          ? 'https://api.myxl.xlaxiata.co.id/api/v1/xl-stores/options/list'
          : 'https://bing.com',
        'headers' => $new_user_id == 'CLI'
          ? $headers
          : ['User-Agent: Mozilla/5.0 (Linux; Android 14; Pixel 6 Pro Build/UPB3.230519.014) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.60 Mobile Safari/537.36 GNews Android/2022137898'],
        'type' => 'http|socks4|socks5',
      ];

      $file = getUserFile($new_user_id);
      write_file($file, json_encode($data));
    }

    // Replace global user ID if different from current
    if ($user_id != $new_user_id) {
      $user_id = $new_user_id;
    }
  }
}

/**
 * Retrieve the current global user ID.
 *
 * @return string The current user ID.
 */
function getUserId(): string
{
  global $user_id;
  return $user_id;
}

if (!file_exists(__DIR__ . '/config')) {
  mkdir(__DIR__ . '/config');
}
setMultiPermissions(__DIR__ . '/config');

function getUserFile(string $user_id): string
{
  return __DIR__ . "/config/$user_id.json";
}

function getUserStatusFile(string $user_id): string
{
  return __DIR__ . "/tmp/status/$user_id.txt";
}

function getUserLogFile(string $user_id): string
{
  return __DIR__ . "/tmp/logs/$user_id.txt";
}

function resetUserLogFile(string $user_id): bool
{
  $user_file = getUserLogFile($user_id);
  $now       = date('Y-m-d H:i:s');
  $content   = "Log reset at $now\n";
  return file_put_contents($user_file, $content, LOCK_EX) !== false;
}

function addUserLog(string $user_id, string $message): bool
{
  $user_file = getUserLogFile($user_id);
  if (!file_exists(dirname($user_file))) {
    mkdir(dirname($user_file), 0777, true);
  }
  if (!file_exists($user_file)) {
    $now    = date('Y-m-d H:i:s');
    $header = "Log created at $now\n";
    file_put_contents($user_file, $header, LOCK_EX);
  }
  return file_put_contents($user_file, date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
}

function getConfig(string $user_id): array
{
  $user_file = getUserFile($user_id);
  if (!file_exists($user_file)) {
    setUserId($user_id);
    $user_file = getUserFile($user_id);
  }
  if (!is_readable($user_file)) {
    setMultiPermissions($user_file, false);
  }
  // Read the JSON file into a string
  $jsonString = read_file($user_file);

  // Decode the JSON string into a PHP array
  $data = json_decode($jsonString, true); // Use true for associative array, false or omit for object

  $defaults = [
    'endpoint' => 'https://google.com',
    'headers'  => [],
    'type'     => 'http|socks4|socks5',
    'user_id'  => $user_id,
  ];

  // Check if decoding was successful
  if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    // Decoding failed
    // echo 'Error decoding JSON: ' . json_last_error_msg();
    return $defaults;
  } else {
    return mergeArrays($defaults, $data);
  }
}

function setConfig($user_id, $data): array
{
  $user_file = getUserFile($user_id);
  $defaults  = getConfig($user_id);
  // remove conflict data
  unset($defaults['headers']);
  // Encode the data to JSON format
  $nData   = mergeArrays($defaults, $data);
  $newData = json_encode($nData);
  // write data
  file_put_contents($user_file, $newData);
  // set permission
  setMultiPermissions($user_file);
  return $nData;
}

/**
 * Check if output buffering is active.
 *
 * @return bool Returns true if output buffering is active, false otherwise.
 */
function is_output_buffering_active()
{
  return ob_get_length() !== false;
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
function mergeArrays(array $arr1, array $arr2): array
{
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
 * Set Cache-Control and Expires headers for HTTP caching.
 *
 * @param int|float $max_age_minutes The maximum age of the cache in minutes.
 * @param bool $cors Whether to include CORS headers. Default is true.
 * @return void
 */
function setCacheHeaders($max_age_minutes, bool $cors = true): void
{
  if ($cors) {
    // Allow from any origin
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: *');
    header('Access-Control-Allow-Methods: *');
  }

  // Set the Cache-Control header to specify the maximum age in minutes and must-revalidate
  $max_age_seconds = $max_age_minutes * 60;
  header('Cache-Control: max-age=' . $max_age_seconds . ', must-revalidate');

  // Set the Expires header to the calculated expiration time
  $expiration_time = time() + $max_age_seconds;
  header('Expires: ' . gmdate('D, d M Y H:i:s', $expiration_time) . ' GMT');
}

/**
 * Prompts the user for confirmation with a message.
 *
 * @param string $message The confirmation message.
 * @return bool True if user confirms (y/yes), false otherwise (n/no).
 */
function confirmAction(string $message = 'Are you sure? (y/n): '): bool
{
  $validResponses = ['y', 'yes', 'n', 'no'];
  $response       = '';

  while (!in_array($response, $validResponses)) {
    echo $message;
    $response = strtolower(trim(fgets(STDIN)));
  }

  return in_array($response, ['y', 'yes']);
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
function iterateArray(array $array, int $limit = 50, ?callable $callback = null): void
{
  $arrayLength = count($array);
  $limit       = min($arrayLength, $limit); // Get the minimum of array length and $limit
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
function fixFile(string $inputFile): void
{
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
      $chunk = fread($fileHandle, 1024 * 1024); // Read in 500KB chunks
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

function isCygwinInstalled()
{
  $cygwinExecutables = ['bash', 'ls']; // List of Cygwin executables to check

  foreach ($cygwinExecutables as $executable) {
    $output = shell_exec("where $executable 2>&1");
    if (strpos($output, 'not found') === false) {
      return true; // Found at least one Cygwin executable
    }
  }

  return false; // None of the Cygwin executables found
}

function runPythonInBackground($scriptPath, $commandArgs = [], $identifier = null)
{
  global $isWin;

  // Convert arguments to command line string
  $commandArgsString = '';
  foreach ($commandArgs as $key => $value) {
    $escapedValue = escapeshellarg($value);
    $commandArgsString .= "--$key=$escapedValue ";
  }
  $commandArgsString = trim($commandArgsString);

  // Determine paths and commands
  $cwd         = __DIR__;
  $filename    = !empty($identifier) ? sanitizeFilename($identifier) : sanitizeFilename(unixPath("$scriptPath/$commandArgsString"));
  $runner      = unixPath(tmp() . "/runners/$filename" . ($isWin ? '.bat' : '.sh'));
  $output_file = unixPath(tmp() . "/logs/$filename.txt");
  $pid_file    = unixPath(tmp() . "/runners/$filename.pid");

  // Truncate output file
  truncateFile($output_file);

  // Construct the command
  $venv     = !$isWin ? realpath("$cwd/venv/bin/activate") : realpath("$cwd/venv/Scripts/activate");
  $venvCall = $isWin ? "call $venv" : "source $venv";

  $cmd = "$venvCall && python $scriptPath $commandArgsString > $output_file 2>&1 & echo $! > $pid_file";
  $cmd = trim($cmd);

  // Write command to runner script
  $write = write_file($runner, $cmd);
  if (!$write) {
    return ['error' => 'Failed writing shell script ' . $runner];
  }

  // Change current working directory
  chdir($cwd);

  // Execute the runner script
  if ($isWin) {
    $runner_win = 'start /B "window_name" ' . escapeshellarg(unixPath($runner));
    pclose(popen($runner_win, 'r'));
  } else {
    exec('bash ' . escapeshellarg($runner) . ' > /dev/null 2>&1 &');
  }

  return [
    'output'   => unixPath($output_file),
    'cwd'      => unixPath($cwd),
    'relative' => str_replace(unixPath($cwd), '', unixPath($output_file)),
    'runner'   => $runner,
  ];
}

function runShellCommandLive($command)
{
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
      flush(); // Make sure the output is sent to the browser immediately
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

/**
 * Executes a Bash or Batch script asynchronously with optional arguments.
 *
 * - Automatically builds a command line from provided arguments.
 * - Writes a shell or batch runner script into the temporary directory.
 * - Uses Python virtual environment activation if available.
 * - Executes the script in the background.
 *
 * By default this function does NOT redirect the script's stdout/stderr into a
 * log file (redirecting is opt-in). When `$redirectOutput` is set to true the
 * script's stdout/stderr will be redirected into a log file located under
 * tmp/logs/<identifier>.txt. Callers that rely on capturing output should set
 * `$redirectOutput` to true.
 *
 * @param string $scriptPath  The path to the Bash (.sh) or Batch (.bat) script.
 * @param array $commandArgs  An associative array of arguments to pass to the script as --key=value.
 * @param string|null $identifier  Optional unique identifier used to name the runner and log files.
 * @param bool $redirectOutput (optional) When true stdout/stderr of the spawned
 *   script will be redirected into the log file. When false (default) the
 *   script will be invoked without redirecting output.
 *
 * @return array{
 *   output: string,     // Full path to the output log file.
 *   cwd: string,        // Current working directory.
 *   relative: string,   // Relative path to the output log file.
 *   runner: string      // Full path to the runner script file.
 * }|array{
 *   error: string       // Error message if script writing fails.
 * }
 */
function runBashOrBatch($scriptPath, $commandArgs = [], $identifier = null, $redirectOutput = false)
{
  global $isWin;

  // Convert arguments to command line string
  $commandArgsString = '';
  foreach ($commandArgs as $key => $value) {
    $escapedValue = escapeshellarg($value);
    $commandArgsString .= "--$key=$escapedValue ";
  }
  $commandArgsString = trim($commandArgsString);

  // Determine paths and commands
  $cwd = __DIR__;

  if (!empty($identifier)) {
    $filename = sanitizeFilename($identifier);
  } else {
    $hash     = md5("$scriptPath/$commandArgsString");
    $name     = pathinfo($scriptPath, PATHINFO_FILENAME);
    $filename = sanitizeFilename($name . '-' . $hash);
  }

  $runner      = unixPath(tmp() . "/runners/$filename" . ($isWin ? '.bat' : '.sh'));
  $output_file = unixPath(tmp() . "/logs/$filename.txt");
  $pid_file    = unixPath(tmp() . "/runners/$filename.pid");

  // Truncate output file
  truncateFile($output_file);

  // Construct the command
  $venv     = !$isWin ? realpath("$cwd/venv/bin/activate") : realpath("$cwd/venv/Scripts/activate");
  $venvCall = $isWin ? "call $venv" : "source $venv";

  $cmd = $venvCall;
  // Optionally ensure output is redirected to the output file and no output is echoed
  if ($redirectOutput) {
    if ($isWin) {
      // On Windows, call the script and redirect stdout/stderr to the log file
      $cmd .= " && call $scriptPath > " . escapeshellarg($output_file) . ' 2>&1';
    } else {
      // On Unix, run the script with bash and redirect stdout/stderr to the log file
      $cmd .= " && bash $scriptPath > " . escapeshellarg($output_file) . ' 2>&1';
    }
  } else {
    // Don't redirect output; just call the script normally
    if ($isWin) {
      $cmd .= " && call $scriptPath";
    } else {
      $cmd .= " && bash $scriptPath";
    }
  }
  $cmd = trim($cmd);

  // Write command to runner script
  $write = write_file($runner, $cmd);
  if (!$write) {
    return ['error' => 'Failed writing shell script ' . $runner];
  }

  // Change current working directory
  chdir($cwd);

  // Execute the runner script
  if ($isWin) {
    // Use start with redirect and /B to run without creating a new window
    // Redirect is already handled inside runner script, ensure command is quoted
    $runner_win = 'start /B "window_name" ' . escapeshellarg(unixPath($runner));
    pclose(popen($runner_win, 'r'));
  } else {
    // Execute the runner script in background; runner already redirects output
    exec('bash ' . escapeshellarg($runner) . ' > /dev/null 2>&1 &');
  }

  return [
    'output'   => unixPath($output_file),
    'cwd'      => unixPath($cwd),
    'relative' => str_replace(unixPath($cwd), '', unixPath($output_file)),
    'runner'   => $runner,
  ];
}

/**
 * Returns the path to the PHP binary executable.
 *
 * This function attempts to determine the path to the currently running PHP binary.
 * It first checks if the PHP_BINARY constant is defined and points to an executable file.
 * If not, it tries to locate the PHP binary using the system's 'which' (Unix) or 'where' (Windows) command.
 * If detection fails, it falls back to returning the string 'php'.
 *
 * @return string The full path to the PHP binary, or 'php' if detection fails.
 */
function getPhpBinaryPath()
{
  // If running from CLI, PHP_BINARY is reliable
  if (php_sapi_name() === 'cli' && is_executable(PHP_BINARY) && stripos(PHP_BINARY, 'php') !== false) {
    return PHP_BINARY;
  }

  // Otherwise, try to detect it manually
  $isWin     = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  $finderCmd = $isWin ? 'where php' : 'which php';
  $found     = trim(shell_exec($finderCmd));

  // Validate the found path
  if (is_executable($found) && stripos($found, 'php') !== false) {
    return $found;
  }

  // Last resort
  return 'php';
}

function safe_json_decode($json, $assoc = true)
{
  $decoded = json_decode($json, $assoc);

  // Check for JSON decode errors
  if (json_last_error() !== JSON_ERROR_NONE) {
    // Return null if there's an error
    return null;
  }

  return $decoded;
}

/**
 * Executes a shell command using the available PHP functions.
 *
 * @param string $cmd The shell command to execute.
 * @return string The command output or an error message.
 */
function php_exec($cmd)
{
  // Check for `exec` support
  if (function_exists('exec')) {
    $output     = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);
    return implode(' ', $output);
  } elseif (function_exists('shell_exec')) {
    // Check for `shell_exec` support
    return shell_exec($cmd);
  } elseif (function_exists('system')) {
    // Check for `system` support
    $return_var = 0;
    ob_start();
    system($cmd, $return_var);
    $output = ob_get_clean();
    return $output;
  } elseif (function_exists('passthru')) {
    // Check for `passthru` support
    $return_var = 0;
    ob_start();
    passthru($cmd, $return_var);
    $output = ob_get_clean();
    return $output;
  } elseif (function_exists('proc_open')) {
    // Check for `proc_open` support
    $descriptorspec = [
      0 => ['pipe', 'r'], // STDIN
      1 => ['pipe', 'w'], // STDOUT
      2 => ['pipe', 'w'], // STDERR
    ];

    $proc = proc_open($cmd, $descriptorspec, $pipes);
    if (is_resource($proc)) {
      $output = stream_get_contents($pipes[1]);
      fclose($pipes[1]);
      fclose($pipes[2]);
      proc_close($proc);
      return $output;
    } else {
      return 'Error: Unable to execute command using proc_open.';
    }
  } else {
    // No suitable function available
    return 'Error: No suitable PHP function available to execute commands.';
  }
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
function remove_array_keys(array &$array, array $keysToRemove): void
{
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
