<?php

/** @noinspection PhpDefineCanBeReplacedWithConstInspection */
/** @noinspection RegExpRedundantEscape */

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!function_exists('str_starts_with')) {
  /**
   * Checks if a string starts with a given prefix using regular expressions.
   *
   * @param string $haystack The input string.
   * @param string $needle The prefix to check for.
   * @return bool Returns true if the string starts with the prefix, false otherwise.
   */
  function str_starts_with(string $haystack, string $needle): bool
  {
    $pattern = '/^' . preg_quote($needle, '/') . '/';
    return (bool)preg_match($pattern, $haystack);
  }
}

define('PHP_PROXY_HUNTER', 'true');

if (!defined('JSON_THROW_ON_ERROR')) {
  define('JSON_THROW_ON_ERROR', 4194304);
}

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
  $paths = [__DIR__ . '/venv/Scripts'];
  $PathSeparator = $isWin ? ";" : ":";
  $newDirectory = implode($PathSeparator, $paths);

  // Combine the current PATH with the new directory
  $newPath = $newDirectory . $PathSeparator . $currentPath;
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

/**
 * Determines whether the application is in debug mode.
 *
 * Debug mode is activated based on several conditions:
 * - If the code is running in a GitHub CI environment or GitHub Codespaces.
 * - If the hostname of the machine matches one of the debug devices specified
 *   in the `DEBUG_DEVICES` environment variable.
 * - If the hostname starts with 'codespaces-'.
 *
 * @return bool True if in debug mode, false otherwise.
 */
function is_debug(): bool
{
  // Check if running in a GitHub CI environment
  $isGitHubCI = getenv('CI') !== false && getenv('GITHUB_ACTIONS') === 'true';

  // Check if running in GitHub Codespaces
  $isGitHubCodespaces = getenv('CODESPACES') === 'true';

  // Debug mode is active in GitHub CI or GitHub Codespaces environments
  if ($isGitHubCI || $isGitHubCodespaces) {
    return true;
  }

  // Get the list of debug devices from the environment variable
  $env_path = __DIR__ . '/.env';
  $debug_pc = [];
  // Verify if the .env file exists
  if (file_exists($env_path)) {
    $read_env = file_get_contents($env_path);
    preg_match('/^DEBUG_DEVICES=(.+)$/m', $read_env, $matches);
    $debug_pc = isset($matches[1]) ? explode(',', $matches[1]) : [];
    $debug_pc = array_map('trim', $debug_pc);
  }

  // Get the hostname of the current machine
  $hostname = gethostname();

  // Debug mode is active if the hostname starts with 'codespaces-'
  if (str_starts_with($hostname, 'codespaces-')) {
    return true;
  }

  // Debug mode is active if the hostname is in the list of debug devices
  return in_array($hostname, $debug_pc);
}

// Detect admin
$isAdmin = is_debug();
if (!$isAdmin) {
  if ($isCli) {
    // CLI
    $short_opts = "p::m::";
    $long_opts = [
      "proxy::",
      "max::",
      "userId::",
      "lockFile::",
      "runner::",
      "admin::"
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
ini_set("log_errors", 1); // Enable error logging
$error_file = __DIR__ . "/tmp/logs/php-error.txt";
if (!$isCli) {
  // Sanitize the user agent string
  $user_agent = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $_SERVER['HTTP_USER_AGENT']);

  // Check if the sanitized user agent is empty
  if (empty($user_agent)) {
    $error_file = __DIR__ . "/tmp/logs/php-error.txt";
  } else {
    $error_file = __DIR__ . "/tmp/logs/php-error-" . $user_agent . ".txt";
  }
}
ini_set("error_log", $error_file); // set error path

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

use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Session;

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$db = new ProxyDB();

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
  $result = [];
  foreach ($array as $item) {
    if (property_exists($item, $property)) {
      $value = $item->$property;
      if (!isset($tempArray[$value])) {
        $tempArray[$value] = true;
        $result[] = $item;
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
    return "Empty string to remove";
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
    return "Failed to open source file";
  }

  // Open destination file for appending
  $destinationHandle = @fopen($destinationFilePath, 'a');
  if (!$destinationHandle) {
    fclose($sourceHandle);
    return "Failed to open destination file";
  }

  // Open a temporary file for writing
  $tempFilePath = tempnam(sys_get_temp_dir(), 'source_temp');
  $tempHandle = @fopen($tempFilePath, 'w');
  if (!$tempHandle) {
    fclose($sourceHandle);
    fclose($destinationHandle);
    return "Failed to create temporary file";
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
      return "Failed to replace source file";
    }

    // Append the removed string to the destination file
    if (file_put_contents($destinationFilePath, PHP_EOL . $stringToRemove . PHP_EOL, FILE_APPEND) === false) {
      return "Failed to append removed string to destination file";
    }

    return "Success";
  } else {
    fclose($sourceHandle);
    fclose($destinationHandle);
    fclose($tempHandle);
    unlink($tempFilePath);
    return "Failed to acquire lock on source file";
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
function curlGetWithProxy(string $url, string $proxy = null, ?string $proxyType = 'http', $cacheTime = 86400 * 360, string $cacheDir = __DIR__ . '/.cache/')
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
  $file = @fopen($filename, "r");
  if (!$file) {
    echo "Error opening $filename for reading" . PHP_EOL;
    return false;
  }

  // Open a temporary file for writing
  $tempFilename = tempnam(__DIR__ . '/tmp', 'rewriteIpPortFile');
  $tempFile = @fopen($tempFilename, "w");
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
    echo "Error replacing original file with temporary file";
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

  $files = [];
  $folder = rtrim($folder, '/') . '/'; // Ensure folder path ends with a slash

  // Open the directory
  if ($handle = opendir($folder)) {
    // Loop through directory contents
    while (false !== ($entry = readdir($handle))) {
      $file = $folder . $entry;

      // ensure it's a file
      if ($entry != "." && $entry != ".." && is_file($file)) {
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
      $parts = explode('=', substr($arg, 2), 2);
      $key = $parts[0];
      $value = $parts[1] ?? true; // If value is not provided, set it to true
      $parsedArgs[$key] = $value;
    }
  }

  return $parsedArgs;
}

// Default user ID to "CLI" assuming the script is running from the command line
$user_id = "CLI";

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
        'Content-Type: application/json; charset=utf-8'
      ];

      $data = [
        'endpoint' => $new_user_id == 'CLI'
          ? 'https://api.myxl.xlaxiata.co.id/api/v1/xl-stores/options/list'
          : 'https://bing.com',
        'headers' => $new_user_id == 'CLI'
          ? $headers
          : ['User-Agent: Mozilla/5.0 (Linux; Android 14; Pixel 6 Pro Build/UPB3.230519.014) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.60 Mobile Safari/537.36 GNews Android/2022137898'],
        'type' => 'http|socks4|socks5'
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

if (!file_exists(__DIR__ . "/config")) {
  mkdir(__DIR__ . "/config");
}
setMultiPermissions(__DIR__ . "/config");

function getUserFile(string $user_id): string
{
  return __DIR__ . "/config/$user_id.json";
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
    'headers' => [],
    'type' => 'http|socks4|socks5',
    'user_id' => $user_id
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
  $defaults = getConfig($user_id);
  // remove conflict data
  unset($defaults['headers']);
  // Encode the data to JSON format
  $nData = mergeArrays($defaults, $data);
  $newData = json_encode($nData);
  // write data
  file_put_contents($user_file, $newData);
  // set permission
  setMultiPermissions($user_file);
  return $nData;
}

/**
 * Anonymize an email address by masking the username.
 *
 * @param string|null $email The email address to anonymize.
 * @return string The anonymized email address.
 */
function anonymizeEmail($email): string
{
  // Return same value when empty
  if (empty($email)) {
    return $email;
  }

  // Split the email into username and domain
  list($username, $domain) = explode('@', $email);

  // Anonymize the username (keep only the first and the last character)
  $username_anon = substr($username, 0, 1) . str_repeat('*', strlen($username) - 2) . substr($username, -1);

  // Reconstruct the anonymized email
  return $username_anon . '@' . $domain;
}

/**
 * Parse incoming POST request data based on Content-Type.
 *
 * @param bool $detect_get Whether to return `$_GET` data if the content type is unsupported (default is false).
 *
 * @return array The parsed POST data.
 */
function parsePostData(bool $detect_get = false): ?array
{
  // Initialize an empty array to store the parsed data
  $result = [];

  // Get the Content-Type header of the request
  $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

  if (strpos($contentType, "multipart/form-data") !== false) {
    // Merge POST fields into the result
    $result = array_merge($result, $_POST);

    // Add uploaded files to the result
    foreach ($_FILES as $key => $file) {
      $result[$key] = $file;
    }
  } elseif (strpos($contentType, "application/json") !== false) {
    // Decode the JSON from the input stream
    $json_data = json_decode(file_get_contents('php://input'), true);

    if (is_array($json_data)) {
      $result = array_merge($result, $json_data);
    }
  } elseif (strpos($contentType, "application/x-www-form-urlencoded") !== false) {
    // For URL-encoded form data, $_POST already contains the parsed data
    $result = array_merge($result, $_POST);
  }

  return $detect_get ? array_merge($_GET, $result) : $result;
}


/**
 * Get the request parameters either from POST data, GET parameters, or a combination.
 *
 * @return array The request parameters array.
 */
function parseQueryOrPostBody(): array
{
  global $isCli;
  if (!$isCli) {
    return array_merge(parsePostData(true), $_REQUEST, $_GET, $_POST);
  }
  return [];
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

function generateRandomString($length = 10): string
{
  $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[rand(0, strlen($characters) - 1)];
  }
  return $randomString;
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
    $callback = $shuffle_or_callback;
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
    $tempFile = tmpfile();
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
        $maxLines = is_callable($callbackOrMax) ? PHP_INT_MAX : $callbackOrMax;
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
        echo "Failed to acquire lock for temporary file" . PHP_EOL;
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
  $sourceHandle = @fopen($sourceFile, "r");
  if (!$sourceHandle) {
    return false; // Unable to open source file
  }

  // Open destination file for reading
  $destinationHandle = @fopen($destinationFile, "r");
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
  $sourceHandle = @fopen($sourceFile, "w");
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

  echo "Splitting complete!";
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
  $handle1 = @fopen($file1, "r");
  $handle2 = @fopen($file2, "r");

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

  $content = read_file($file_path);
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
    return "removeStringFromFile: preg_replace failed";
  }
  if ($count === 0) {
    return "removeStringFromFile: no string replaced";
  }

  $result = file_put_contents($file_path, $new_string);
  if ($result === false) {
    return 'removeStringFromFile: failed to write to file';
  }

  return 'success';
}

/**
 * Check if a file was created a certain number of hours ago.
 *
 * @param string $filePath The path to the file.
 * @param int $hours The number of hours ago to check against.
 * @return bool True if the file was created more than the specified number of hours ago, otherwise false.
 */
function isFileCreatedMoreThanHours(string $filePath, int $hours): bool
{
  // Check if the file exists
  if (!file_exists($filePath)) {
    return false;
  }

  // Get the file creation time
  $creationTime = filectime($filePath);

  // Calculate the time difference in seconds
  $differenceInSeconds = time() - $creationTime;

  // Calculate the time threshold in seconds
  $thresholdInSeconds = $hours * 60 * 60;

  // Check if the file was created more than the specified time frame
  return $differenceInSeconds > $thresholdInSeconds;
}

/**
 * Converts a string or an array of strings into regex patterns.
 *
 * @param string|array $input The input string or array of strings.
 * @return string|array The regex pattern(s) corresponding to the input.
 */
function string_to_regex($input)
{
  // If $input is an array, process each string
  if (is_array($input)) {
    return array_map(function ($string) {
      return '/\b' . preg_quote($string, '/') . '\b/';
    }, $input);
  } else { // If $input is a single string, process it
    return '/\b' . preg_quote($input, '/') . '\b/';
  }
}

function getIPRange(string $cidr): array
{
  list($ip, $mask) = explode('/', trim($cidr));

  $ipLong = ip2long($ip);
  $maskLong = ~((1 << (32 - $mask)) - 1);

  $start = $ipLong & $maskLong;
  $end = $ipLong | (~$maskLong & 0xFFFFFFFF);

  $ips = [];
  for ($i = $start; $i <= $end; $i++) {
    $ip = long2ip($i);
    if (is_string($ip)) {
      $ips[] = trim($ip);
    }
  }

  return $ips;
}

// Example usage
// $cidr = "159.21.130.0/24";
// $ipList = getIPRange($cidr);

// foreach ($ipList as $ip) {
//   echo $ip . "\n";
// }

function IPv6CIDRToRange($cidr): array
{
  list($ip, $prefix) = explode('/', $cidr);
  $range_start = inet_pton($ip);
  $range_end = $range_start;

  if ($prefix < 128) {
    $suffix = 128 - $prefix;
    for ($i = 0; $i < $suffix; $i++) {
      $range_start[$i] = chr(ord($range_start[$i]) & (0xFF << ($i % 8)));
      $range_end[$i] = chr(ord($range_end[$i]) | (0xFF >> (7 - $i % 8)));
    }
  }

  return [
    'start' => inet_ntop($range_start),
    'end' => inet_ntop($range_end)
  ];
}

// function IPv6CIDRToList($cidr)
// {
//   $range = IPv6CIDRToRange($cidr);
//   $start = inet_pton($range['start']);
//   $end = inet_pton($range['end']);
//   $ips = array();
//   while (strcmp($start, $end) <= 0) {
//     $ips[] = inet_ntop($start);
//     $start = gmp_add($start, 1);
//   }
//   return $ips;
// }

function IPv6CIDRToList($cidr): array
{
  $range = IPv6CIDRToRange($cidr);
  $start = inet_pton($range['start']);
  $end = inet_pton($range['end']);
  $ips = [];

  // Increment IP address in binary representation
  while (strcmp($start, $end) <= 0) {
    $ips[] = inet_ntop($start);
    // Increment binary representation of IP address
    for ($i = strlen($start) - 1; $i >= 0; $i--) {
      $start[$i] = chr(ord($start[$i]) + 1);
      if ($start[$i] != chr(0)) {
        break;
      }
    }
  }
  return $ips;
}

// Example usage
// $cidr = '2404:6800:4000::/36';
// $ips = IPv6CIDRToList($cidr);
// foreach ($ips as $ip) {
//   echo "$ip\n";
// }

/**
 * Generates a random user agent string for Windows operating system.
 *
 * @return string Random user agent string.
 */
function randomWindowsUa(): string
{
  // Array of Windows versions
  $windowsVersions = ['Windows 7', 'Windows 8', 'Windows 10', 'Windows 11'];

  // Array of Chrome versions
  $chromeVersions = [
    '86.0.4240',
    '98.0.4758',
    '100.0.4896',
    '105.0.5312',
    '110.0.5461',
    '115.0.5623',
    '120.0.5768',
    '124.0.6367.78', // Windows and Linux version
    '124.0.6367.79', // Mac version
    '124.0.6367.82', // Android version
  ];

  // Randomly select a Windows version
  $randomWindows = $windowsVersions[array_rand($windowsVersions)];

  // Randomly select a Chrome version
  $randomChrome = $chromeVersions[array_rand($chromeVersions)];

  // Generate random Safari version and AppleWebKit version
  $randomSafariVersion = mt_rand(600, 700) . '.' . mt_rand(0, 99);
  $randomAppleWebKitVersion = mt_rand(500, 600) . '.' . mt_rand(0, 99);

  // Construct and return the user agent string
  return "Mozilla/5.0 ($randomWindows) AppleWebKit/$randomAppleWebKitVersion (KHTML, like Gecko) Chrome/$randomChrome Safari/$randomSafariVersion";
}

/**
 * Generates a random Android user-agent string.
 *
 * @param string $type The type of browser user-agent to generate. Default is 'chrome'.
 * @return string The generated user-agent string.
 */
function randomAndroidUa(string $type = 'chrome'): string
{
  // Android version array
  $androidVersions = [
    '10.0' => 'Android Q',
    '11.0' => 'Red Velvet Cake',
    '12.0' => 'Snow Cone',
    '12.1' => 'Snow Cone v2',
    '13.0' => 'Tiramisu',
    '14.0' => 'Upside Down Cake',
  ];

  // Random Android version
  $androidVersion = array_rand($androidVersions);

  // Random device manufacturer and model
  $manufacturers = ['Samsung', 'Google', 'Huawei', 'Xiaomi', 'LG'];
  $models = [
    'Samsung' => [
      'Galaxy S20',
      'Galaxy Note 10',
      'Galaxy A51',
      'Galaxy S10',
      'Galaxy S9',
      'Galaxy Note 9',
      'Galaxy S21',
      'Galaxy Note 20',
      'Galaxy Z Fold 2',
      'Galaxy A71',
      'Galaxy S20 FE'
    ],
    'Google' => ['Pixel 4', 'Pixel 3a', 'Pixel 3', 'Pixel 5', 'Pixel 4a', 'Pixel 4 XL', 'Pixel 3 XL'],
    'Huawei' => ['P30 Pro', 'Mate 30', 'P40', 'Mate 40 Pro', 'P40 Pro', 'Mate Xs', 'Nova 7i'],
    'Xiaomi' => [
      'Mi 10',
      'Redmi Note 9',
      'POCO F2 Pro',
      'Mi 11',
      'Redmi Note 10 Pro',
      'POCO X3',
      'Mi 10T Pro',
      'Redmi Note 4x',
      'Redmi Note 5',
      'Redmi 6a',
      'Mi 8 Lite'
    ],
    'LG' => ['G8 ThinQ', 'V60 ThinQ', 'Stylo 6', 'Velvet', 'Wing', 'K92', 'Q92'],
  ];

  $manufacturer = $manufacturers[array_rand($manufacturers)];
  $model = $models[$manufacturer][array_rand($models[$manufacturer])];

  // Random version numbers for AppleWebKit, Chrome, and Mobile Safari
  $appleWebKitVersion = mt_rand(500, 700) . '.' . mt_rand(0, 99);
  $chromeVersion = mt_rand(70, 99) . '.0.' . mt_rand(1000, 9999);
  $mobileSafariVersion = mt_rand(500, 700) . '.' . mt_rand(0, 99);

  // Generate chrome user-agent string
  $chrome = "Mozilla/5.0 (Linux; Android $androidVersion; $manufacturer $model) AppleWebKit/$appleWebKitVersion (KHTML, like Gecko) Chrome/$chromeVersion Mobile Safari/$mobileSafariVersion";

  // Random Firefox version
  $firefoxVersion = mt_rand(60, 90) . '.0';

  // Generate firefox user-agent string for Mozilla Firefox on Android with randomized version
  $firefoxModel = getRandomItemFromArray(['Mobile', 'Tablet']);
  $firefox = "Mozilla/5.0 (Android $androidVersion; $firefoxModel; rv:$firefoxVersion) Gecko/$firefoxVersion Firefox/$firefoxVersion";

  return $type == 'chrome' ? $chrome : $firefox;
}

/**
 * Generates a random iOS user-agent string.
 *
 * @param string $type The type of browser user-agent to generate. Default is 'chrome'.
 * @return string The generated user-agent string.
 */
function randomIosUa(string $type = 'chrome'): string
{
  $chrome_version = rand(70, 100);
  $ios_version = rand(9, 15);
  $safari_version = rand(600, 700);
  $build_version = "15E" . rand(100, 999);

  $chrome = "Mozilla/5.0 (iPhone; CPU iPhone OS $ios_version like Mac OS X) AppleWebKit/$safari_version.1 (KHTML, like Gecko) CriOS/$chrome_version.0.0.0 Mobile/$build_version Safari/$safari_version.1";

  $firefox_version = rand(80, 100);

  $firefox = "Mozilla/5.0 (iPhone; CPU iPhone OS $ios_version like Mac OS X) AppleWebKit/$safari_version.1 (KHTML, like Gecko) FxiOS/$firefox_version.0 Mobile/$build_version Safari/$safari_version.1";

  return $type == 'chrome' ? $chrome : $firefox;
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
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: *");
    header("Access-Control-Allow-Methods: *");
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
function confirmAction(string $message = "Are you sure? (y/n): "): bool
{
  $validResponses = ['y', 'yes', 'n', 'no'];
  $response = '';

  while (!in_array($response, $validResponses)) {
    echo $message;
    $response = strtolower(trim(fgets(STDIN)));
  }

  return in_array($response, ['y', 'yes']);
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
 * Scans a range of ports on a given IP address and returns an array of proxies.
 *
 * @param string $ip The IP address to scan ports on.
 * @param int $startPort The starting port of the range (default is 1).
 * @param int $endPort The ending port of the range (default is 65535).
 * @return array An array containing the proxies found during scanning.
 */
function scanRangePorts(string $ip, int $startPort = 1, int $endPort = 65535): array
{
  $proxies = [];
  for ($port = $startPort; $port <= $endPort; $port++) {
    if (scanPort($ip, $port)) {
      $proxies[] = "$ip:$port";
    }
  }
  return $proxies;
}

/**
 * Scans an array of specific ports on a given IP address and returns an array of proxies.
 *
 * @param string $ip The IP address to scan ports on.
 * @param array $ports An array containing the ports to scan.
 * @return array An array containing the proxies found during scanning.
 */
function scanArrayPorts(string $ip, array $ports): array
{
  $proxies = [];
  foreach ($ports as $port) {
    if (scanPort($ip, $port)) {
      $proxies[] = "$ip:$port";
    }
  }
  return $proxies;
}

/**
 * Scans a specific port on a given IP address.
 *
 * @param string $ip The IP address to scan the port on.
 * @param int $port The port to scan.
 * @return bool Returns true if the port is open, false otherwise.
 */
function scanPort(string $ip, int $port): bool
{
  $ip = trim($ip);
  echo "Scanning port $ip:$port\n";
  $connection = @fsockopen($ip, $port, $errno, $error_string, 10);
  if (is_resource($connection)) {
    echo "Port $port is open.\n";
    fclose($connection);
    return true;
  }
  return false;
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
  $lines = [];
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
  $limit = min($arrayLength, $limit); // Get the minimum of array length and $limit
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
  $file = @fopen($filePath, "r");
  if (!$file) {
    return false; // File open failed
  }

  $count = 0;
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
    echo "fixFile: Unable to acquire lock." . PHP_EOL;
  }
}

/**
 * Split a string into an array of lines. Support CRLF
 *
 * @param string|null $str The input string to split.
 * @return array An array of lines, or an empty array if the split fails.
 */
function split_by_line(?string $str): array
{
  if (!$str) {
    return [];
  }

  $lines = preg_split('/\r?\n/', $str);

  // Check if preg_split succeeded
  if ($lines === false) {
    return [];
  }

  return $lines;
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
  $lockSource = $sourceHandle && flock($sourceHandle, LOCK_SH);
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

    return "success"; // Success, so return "success"
  } else {
    // Close both files if they were opened
    if ($sourceHandle) {
      fclose($sourceHandle);
    }
    if ($destinationHandle) {
      fclose($destinationHandle);
    }

    return "Failed to move content"; // Indicate failure
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
  $fd = @fopen($inputFile, "r");
  if ($fd === false) {
    echo "removeDuplicateLines: Failed to open $inputFile" . PHP_EOL;
    return;
  }
  if (flock($fd, LOCK_EX)) { // Acquire an exclusive lock
    while ($line = fgets($fd)) {
      $line = rtrim($line, "\r\n"); // ignore the newline
      $lines[$line] = 1;
    }
    flock($fd, LOCK_UN); // Release the lock
  }
  fclose($fd);
  $fd = @fopen($inputFile, "w");
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
  $cwd = __DIR__;
  $filename = !empty($identifier) ? sanitizeFilename($identifier) : sanitizeFilename(unixPath("$scriptPath/$commandArgsString"));
  $runner = unixPath(tmp() . "/runners/$filename" . ($isWin ? ".bat" : ".sh"));
  $output_file = unixPath(tmp() . "/logs/$filename.txt");
  $pid_file = unixPath(tmp() . "/runners/$filename.pid");

  // Truncate output file
  truncateFile($output_file);

  // Construct the command
  $venv = !$isWin ? realpath("$cwd/venv/bin/activate") : realpath("$cwd/venv/Scripts/activate");
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
    $runner_win = "start /B \"window_name\" " . escapeshellarg(unixPath($runner));
    pclose(popen($runner_win, 'r'));
  } else {
    exec("bash " . escapeshellarg($runner) . " > /dev/null 2>&1 &");
  }

  return [
    'output' => unixPath($output_file),
    'cwd' => unixPath($cwd),
    'relative' => str_replace(unixPath($cwd), '', unixPath($output_file)),
    'runner' => $runner
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
    2 => ['pipe', 'w']  // stderr
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

function runBashOrBatch($scriptPath, $commandArgs = [], $identifier = null)
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
  $filename = sanitizeFilename(!empty($identifier) ? $identifier : unixPath("$scriptPath/$commandArgsString"));
  $runner = unixPath(tmp() . "/runners/$filename" . ($isWin ? ".bat" : ".sh"));
  $output_file = unixPath(tmp() . "/logs/$filename.txt");
  $pid_file = unixPath(tmp() . "/runners/$filename.pid");

  // Truncate output file
  truncateFile($output_file);

  // Construct the command
  $venv = !$isWin ? realpath("$cwd/venv/bin/activate") : realpath("$cwd/venv/Scripts/activate");
  $venvCall = $isWin ? "call $venv" : "source $venv";

  $cmd = $venvCall;
  if ($isWin) {
    $cmd .= " && call $scriptPath";
  } else {
    $cmd .= " && bash $scriptPath";
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
    $runner_win = "start /B \"window_name\" " . escapeshellarg(unixPath($runner));
    pclose(popen($runner_win, 'r'));
  } else {
    exec("bash " . escapeshellarg($runner) . " > /dev/null 2>&1 &");
  }

  return [
    'output' => unixPath($output_file),
    'cwd' => unixPath($cwd),
    'relative' => str_replace(unixPath($cwd), '', unixPath($output_file)),
    'runner' => $runner
  ];
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
    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);
    return implode(" ", $output);
  }
  // Check for `shell_exec` support
  elseif (function_exists('shell_exec')) {
    return shell_exec($cmd);
  }
  // Check for `system` support
  elseif (function_exists('system')) {
    $return_var = 0;
    ob_start();
    system($cmd, $return_var);
    $output = ob_get_clean();
    return $output;
  }
  // Check for `passthru` support
  elseif (function_exists('passthru')) {
    $return_var = 0;
    ob_start();
    passthru($cmd, $return_var);
    $output = ob_get_clean();
    return $output;
  }
  // Check for `proc_open` support
  elseif (function_exists('proc_open')) {
    $descriptorspec = [
      0 => ["pipe", "r"], // STDIN
      1 => ["pipe", "w"], // STDOUT
      2 => ["pipe", "w"], // STDERR
    ];

    $proc = proc_open($cmd, $descriptorspec, $pipes);
    if (is_resource($proc)) {
      $output = stream_get_contents($pipes[1]);
      fclose($pipes[1]);
      fclose($pipes[2]);
      proc_close($proc);
      return $output;
    } else {
      return "Error: Unable to execute command using proc_open.";
    }
  }
  // No suitable function available
  else {
    return "Error: No suitable PHP function available to execute commands.";
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
