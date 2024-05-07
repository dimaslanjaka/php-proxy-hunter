<?php

define('PHP_PROXY_HUNTER', date(DATE_RFC3339));
if (!defined('JSON_THROW_ON_ERROR')) {
  define('JSON_THROW_ON_ERROR', 4194304);
}

// debug all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set("log_errors", 1); // Enable error logging
ini_set("error_log", __DIR__ . "/tmp/php-error.log"); // set error path
error_reporting(E_ALL);

// ignore limitation if exists
if (function_exists('set_time_limit')) {
  call_user_func('set_time_limit', 0);
}

require_once __DIR__ . '/vendor/autoload.php';

use PhpProxyHunter\ProxyDB;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$db = new ProxyDB();

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

// keep running when user closed the connection (true)
// ignore_user_abort(true);
// ignore user abort execution to false
if (function_exists('ignore_user_abort')) {
  call_user_func('ignore_user_abort', false);
}

// set default timezone
date_default_timezone_set('Asia/Jakarta');

// allocate memory
ini_set('memory_limit', '128M');

// start session
if (!$isCli) {
  // Check if session is not already started
  if (session_status() === PHP_SESSION_NONE) {
    // Start the session
    session_start();
  }
}

// create temp folder
if (!file_exists(__DIR__ . '/tmp'))
  mkdir(__DIR__ . '/tmp');

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
 * Sets file permissions to 777 if the file exists.
 *
 * @param string|array $filenames The filename(s) to set permissions for.
 *                                Can be a single filename or an array of filenames.
 *
 * @return void
 */
function setFilePermissions($filenames)
{
  if (is_array($filenames)) {
    foreach ($filenames as $filename) {
      setPermissions($filename);
    }
  } elseif (is_string($filenames)) {
    setPermissions($filenames);
  } else {
    echo "Invalid parameter type. Expected string or array.\n";
  }
}

/**
 * Sets file permissions to 777 if the file exists.
 *
 * @param string $filename The filename to set permissions for.
 *
 * @return void
 */
function setPermissions(string $filename)
{
  try {
    if (file_exists($filename) && is_readable($filename) && is_writable($filename)) {
      chmod($filename, 0777);
    }
  } catch (\Throwable $th) {
    //throw $th;
  }
}

function removeStringAndMoveToFile($sourceFilePath, $destinationFilePath, $stringToRemove): bool
{
  //  echo "moving $stringToRemove from $sourceFilePath to $destinationFilePath" . PHP_EOL;
  if (!is_writable($sourceFilePath) && !is_writable($destinationFilePath)) {
    echo "$sourceFilePath or $destinationFilePath not writable" . PHP_EOL;
    return false;
  }

  // Read content from the source file
  $sourceContent = read_file($sourceFilePath);

  if (strpos($sourceContent, $stringToRemove) !== false) {
    // Remove the desired string
    $modifiedContent = str_replace($stringToRemove, '', $sourceContent);

    // Write the modified content back to the source file
    $writeSrc = file_put_contents($sourceFilePath, $modifiedContent);

    // Append the removed string to the destination file
    $writeDest = file_put_contents($destinationFilePath, PHP_EOL . $stringToRemove . PHP_EOL, FILE_APPEND);

    return $writeSrc != false && $writeDest != false;
  }
  return false;
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
  if (!file_exists($cacheDir))
    mkdir($cacheDir);
  $cacheFile = $cacheDir . md5($url);

  // Check if cached data exists and is still valid
  if ($cacheTime > 0 && file_exists($cacheFile) && time() - filemtime($cacheFile) < $cacheTime) {
    // Return cached response
    return read_file($cacheFile);
  }

  // Initialize cURL session
  $ch = curl_init();

  // Set the URL
  curl_setopt($ch, CURLOPT_URL, $url);

  // Set proxy details
  if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    $type = CURLPROXY_HTTP;
    if ($proxyType == 'socks4')
      $type = CURLPROXY_SOCKS4;
    if ($proxyType == 'socks5')
      $type = CURLPROXY_SOCKS5;
    curl_setopt($ch, CURLOPT_PROXYTYPE, $type);
  }

  // Set to return the transfer as a string
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  // Execute the request
  $response = curl_exec($ch);

  // Check for errors
  if (curl_errno($ch)) {
    // echo 'Error: ' . curl_error($ch);
  } else {
    // Save response to cache file
    if ($cacheTime > 0) {
      file_put_contents($cacheFile, $response);
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
function rewriteIpPortFile($filename): bool
{
  if (!file_exists($filename) || !is_readable($filename) || !is_writable($filename)) {
    echo "File '$filename' is not readable or writable" . PHP_EOL;
    return false;
  }

  // Open the file for reading
  $file = fopen($filename, "r");
  if (!$file) {
    echo "Error opening $filename for reading" . PHP_EOL;
    return false;
  }

  // Open a temporary file for writing
  $tempFilename = tempnam(__DIR__ . '/tmp', 'rewriteIpPortFile');
  $tempFile = fopen($tempFilename, "w");
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
      fwrite($tempFile, $match . "\n");
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
  $file = fopen($filename, 'r');

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
 * Check if a given date string in RFC3339 format is older than the specified number of hours.
 *
 * @param string $dateString The date string in DATE_RFC3339 format.
 * @param int $hoursAgo The number of hours to compare against.
 * @return bool True if the date is older than the specified number of hours, false otherwise.
 */
function isDateRFC3339OlderThanHours(string $dateString, int $hoursAgo): bool
{
  // Create a DateTime object from the string
  $date = new DateTime($dateString);

  // Create a DateTime object representing the specified number of hours ago
  $hoursAgoDateTime = new DateTime();
  $hoursAgoDateTime->sub(new DateInterval('PT' . $hoursAgo . 'H'));

  // Compare the date with the specified number of hours ago
  return $date < $hoursAgoDateTime;
}

/**
 * Extracts IP:PORT combinations from a file.
 *
 * @param string $filePath The path to the file containing IP:PORT combinations.
 * @param bool $unique (Optional) If set to true, returns only unique IP:PORT combinations. Default is false.
 * @return array An array containing the extracted IP:PORT combinations.
 */
function extractIpPortFromFile($filePath, bool $unique = false): array
{
  $ipPortList = [];

  if (file_exists($filePath)) {
    // Open the file for reading in binary mode
    $fp = fopen($filePath, "rb");
    if (!$fp) {
      throw new Exception('File open failed.');
    }

    // Read file line by line
    while (!feof($fp)) {
      $line = fgets($fp);

      // Match IP:PORT pattern using regular expression
      preg_match_all('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+\b/', $line, $matches);

      // Add matched IP:PORT combinations to the list
      foreach ($matches[0] as $match) {
        $ipPortList[] = trim($match);
      }
    }

    // Close the file
    fclose($fp);
  }

  if ($unique) {
    $ipPortList = array_unique($ipPortList);
  }

  return $ipPortList;
}

/**
 * Extracts IP:PORT combinations from a file and processes each match using a callback function.
 *
 * @param string $filePath The path to the file containing IP:PORT combinations.
 * @param callable $callback The callback function to process each matched IP:PORT combination.
 */
function extractIpPortFromFileCallback($filePath, callable $callback)
{
  if (file_exists($filePath)) {
    // Open the file for reading in binary mode
    $fp = fopen($filePath, "rb");
    if (!$fp) {
      throw new Exception('File open failed.');
    }

    // Read file line by line
    while (!feof($fp)) {
      $line = fgets($fp);

      // Match IP:PORT pattern using regular expression
      preg_match_all('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+\b/', $line, $matches);

      // Process each matched IP:PORT combination using the callback function
      foreach ($matches[0] as $match) {
        $proxy = trim($match);
        if (empty($proxy) || is_null($proxy))
          continue;
        $callback($proxy);
      }
    }

    // Close the file
    fclose($fp);
  }
}

// Function to parse command line arguments
function parseArgs($args): array
{
  $parsedArgs = [];
  $currentKey = null;

  foreach ($args as $arg) {
    if (substr($arg, 0, 2) === '--') {
      // Argument is in the format --key=value
      $parts = explode('=', substr($arg, 2), 2);
      $key = $parts[0];
      $value = isset($parts[1]) ? $parts[1] : true; // If value is not provided, set it to true
      $parsedArgs[$key] = $value;
    } elseif (substr($arg, 0, 1) === '-') {
      // Handle other types of arguments if needed
      // For example, arguments in the format -k value
      // Add your implementation here if needed
    } else {
      // Argument is not prefixed with -- or -, treat it as a value
      // You can handle this case if needed
    }
  }

  return $parsedArgs;
}

$user_id = "CLI";
if (!$isCli) {
  $user_id = session_id();
} else {
  $parsedArgs = parseArgs($argv);
  if (isset($parsedArgs['userId']) && !empty(trim($parsedArgs['userId']))) {
    $user_id = trim($parsedArgs['userId']);
  }
}

setUserId($user_id);

function setUserId(string $new_user_id)
{
  global $user_id;
  $user_file = !empty($new_user_id) ? getUserFile($new_user_id) : null;
  if ($user_file != null) {
    if (!file_exists(dirname($user_file)))
      mkdir(dirname($user_file), 0777, true);
    // write default user config
    if (!file_exists($user_file)) {
      $headers = array(
          'X-Dynatrace: MT_3_6_2809532683_30-0_24d94a15-af8c-49e7-96a0-1ddb48909564_0_1_619',
          'X-Api-Key: vT8tINqHaOxXbGE7eOWAhA==',
          'Authorization: Bearer',
          'X-Request-Id: 63337f4c-ec03-4eb8-8caa-4b7cd66337e3',
          'X-Request-At: 2024-04-07T20:57:14.73+07:00',
          'X-Version-App: 5.8.8',
          'User-Agent: myXL / 5.8.8(741); StandAloneInstall; (samsung; SM-G955N; SDK 25; Android 7.1.2)',
          'Content-Type: application/json; charset=utf-8'
      );
      $data = array(
          'endpoint' => $new_user_id == 'CLI' ? 'https://api.myxl.xlaxiata.co.id/api/v1/xl-stores/options/list' : 'https://bing.com',
          'headers' => $new_user_id == 'CLI' ? $headers : ['User-Agent: Mozilla/5.0 (Linux; Android 14; Pixel 6 Pro Build/UPB3.230519.014) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.60 Mobile Safari/537.36 GNews Android/2022137898'],
          'type' => 'http|socks4|socks5'
      );
      $file = getUserFile($new_user_id);
      file_put_contents($file, json_encode($data));
    }
    // replace global user id
    if ($user_id != $new_user_id)
      $user_id = $new_user_id;
  }
}

function getUserId(): string
{
  global $user_id;
  return $user_id;
}

if (!file_exists(__DIR__ . "/config")) {
  mkdir(__DIR__ . "/config");
}
setFilePermissions(__DIR__ . "/config");
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
  // Read the JSON file into a string
  $jsonString = read_file($user_file);

  // Decode the JSON string into a PHP array
  $data = json_decode($jsonString, true); // Use true for associative array, false or omit for object

  $defaults = array(
      'endpoint' => 'https://google.com',
      'headers' => [],
      'type' => 'http|socks4|socks5',
      'user_id' => $user_id
  );

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
  setFilePermissions($user_file);
  return $nData;
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

    $file = fopen($filePath, 'r');
    if ($file) {
      $maxLines = is_callable($callbackOrMax) ? PHP_INT_MAX : $callbackOrMax;
      $linesRead = 0;

      while (($line = fgets($file)) !== false && $linesRead < $maxLines) {
        // Execute callback for each line if $callbackOrMax is a callback
        if (is_callable($callbackOrMax)) {
          call_user_func($callbackOrMax, $line);
        } elseif (is_callable($callback)) {
          // Execute callback for each line if $callback is provided
          call_user_func($callback, $line);
        }

        $linesRead++;
      }
      fclose($file);
    } else {
      echo "failed open $filePath" . PHP_EOL;
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
  // Read destination file into an array
  $destinationLines = file($destinationFile, FILE_IGNORE_NEW_LINES);

  // Open source file for reading and writing
  $sourceHandle = fopen($sourceFile, "r+");
  if (!$sourceHandle) {
    return false; // Unable to open source file
  }

  // Create a temporary file to store non-duplicated lines
  $tempFile = tmpfile();

  // Read lines from source file
  while (($line = fgets($sourceHandle)) !== false) {
    // Check if the line exists in the destination file
    if (!in_array(trim($line), $destinationLines)) {
      // If not, write the line to the temporary file
      fwrite($tempFile, $line);
    }
  }

  // Rewind the temporary file pointer
  rewind($tempFile);

  // Truncate the source file
  ftruncate($sourceHandle, 0);

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
  $handle = fopen($largeFilePath, 'r');

  // Counter for the lines read
  $lineCount = 0;

  // Counter for the small file index
  $fileIndex = 1;

  // Create the first small file
  $smallFile = fopen($outputDirectory . '/' . $filename . '_part_' . $fileIndex . '.txt', 'w');

  // Loop through the large file line by line
  while (!feof($handle)) {
    $line = fgets($handle);

    // Write the line to the current small file
    fwrite($smallFile, $line);

    // Increment the line count
    $lineCount++;

    // Check if reached maximum lines per small file
    if ($lineCount >= $maxLinesPerFile) {
      // Close the current small file
      fclose($smallFile);

      // Increment the file index
      $fileIndex++;

      // Open a new small file
      $smallFile = fopen($outputDirectory . '/' . $filename . '_part_' . $fileIndex . '.txt', 'w');

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
  $handle1 = fopen($file1, "r");
  $handle2 = fopen($file2, "r");

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
 * Remove duplicate lines from a file.
 *
 * @param string $filePath The path to the file.
 * @return void
 */
function removeDuplicateLines(string $filePath): void
{
  // Open the file for reading and writing
  $fileHandle = fopen($filePath, 'r+');

  if ($fileHandle !== false && flock($fileHandle, LOCK_EX)) { // Acquire an exclusive lock
    // Initialize an array to store unique lines
    $uniqueLines = [];

    // Read the file line by line
    while (!feof($fileHandle)) {
      $line = fgets($fileHandle);

      // Trim whitespace and remove empty lines
      $trimmedLine = trim($line);
      if ($trimmedLine !== '') {
        // If the line is not already in the unique lines array, add it
        if (!in_array($trimmedLine, $uniqueLines, true)) {
          $uniqueLines[] = $trimmedLine;
        }
      }
    }

    // Rewind the file pointer to the beginning of the file
    rewind($fileHandle);

    // Truncate the file (clear its contents)
    ftruncate($fileHandle, 0);

    // Write the unique lines back to the file
    foreach ($uniqueLines as $uniqueLine) {
      fwrite($fileHandle, $uniqueLine . PHP_EOL);
    }

    // Release the lock and close the file handle
    flock($fileHandle, LOCK_UN);
    fclose($fileHandle);
  } else {
    // Handle error opening or locking the file
    // You can log an error or throw an exception here
    echo "Failed remove duplicated lines from $filePath" . PHP_EOL;
  }
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
    } else if (isset($arr1[$key]) && is_array($arr1[$key]) && is_array($arr2[$key])) {
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
function removeEmptyLinesFromFile($filePath)
{
  // Check if the file exists and is readable
  if (!file_exists($filePath) || !is_readable($filePath)) {
    // echo "Error: The file '$filePath' does not exist or cannot be read." . PHP_EOL;
    return;
  }

  // Open the file for reading
  $inputFile = fopen($filePath, 'r');
  if (!$inputFile) {
    // echo "Error: Unable to open file for reading: $filePath" . PHP_EOL;
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
    if (trim($line) !== '') {
      fwrite($tempFile, $line);
    }
  }

  // Close both files
  fclose($inputFile);
  rewind($tempFile);

  // Rewrite the content of the input file with the content of the temporary file
  $outputFile = fopen($filePath, 'w');
  if (!$outputFile) {
    // echo "Error: Unable to open file for writing: $filePath" . PHP_EOL;
    fclose($tempFile);
    return;
  }

  // Copy content from temporary file to input file
  while (($line = fgets($tempFile)) !== false) {
    fwrite($outputFile, $line);
  }

  // Close the temporary file and the output file
  fclose($tempFile);
  fclose($outputFile);
}

/**
 * Move content from a source file to a destination file in append mode.
 *
 * @param string $sourceFile The path to the source file.
 * @param string $destinationFile The path to the destination file.
 *
 * @return bool True if the content was moved successfully, false otherwise.
 */
function moveContent($sourceFile, $destinationFile): bool
{
  // Open the source file for reading
  $sourceHandle = fopen($sourceFile, 'r');

  // Open the destination file for appending
  $destinationHandle = fopen($destinationFile, 'a');

  // Check if both files are opened successfully
  if ($sourceHandle && $destinationHandle) {
    // Read content from the source file and write it to the destination file
    while (($line = fgets($sourceHandle)) !== false) {
      fwrite($destinationHandle, $line);
    }

    // Close both files
    fclose($sourceHandle);
    fclose($destinationHandle);

    return true; // Indicate success
  } else {
    return false; // Indicate failure
  }
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
  $sourceHandle = fopen($sourceFile, 'r+');
  if (!$sourceHandle) {
    return false;
  }

  // Lock the source file
  flock($sourceHandle, LOCK_EX);

  // Open or create the destination file for appending
  $destinationHandle = fopen($destinationFile, 'a');
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
    fwrite($destinationHandle, $line);
  }

  // Remove the moved lines from the source file
  $remainingContent = '';
  while (!feof($sourceHandle)) {
    $remainingContent .= fgets($sourceHandle);
  }
  ftruncate($sourceHandle, 0); // Clear the file
  rewind($sourceHandle);
  fwrite($sourceHandle, $remainingContent);

  // Close the file handles
  fclose($sourceHandle);
  fclose($destinationHandle);

  return true;
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
  // Open the file for appending, create it if it doesn't exist
  $handle = fopen($file, 'a+');

  // Check if file handle is valid
  if (!$handle) {
    return false;
  }

  // Acquire an exclusive lock
  if (flock($handle, LOCK_EX)) {
    // Append the content
    fwrite($handle, $content_to_append);

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
 * Removes a specified string from a text file.
 *
 * @param string $file_path The path to the text file.
 * @param string $string_to_remove The string to remove from the file.
 * @return bool True if the string was successfully removed, false otherwise.
 */
function removeStringFromFile(string $file_path, string $string_to_remove): bool
{
  // Open the file in read mode
  $file_handle = fopen($file_path, 'r');

  if (!$file_handle) {
    return false; // Unable to open file
  }

  // Create a temporary file to write modified content
  $temp_file_path = tempnam(sys_get_temp_dir(), 'tempfile');

  // Open the temporary file in write mode
  $temp_file_handle = fopen($temp_file_path, 'w');

  if (!$temp_file_handle) {
    fclose($file_handle);
    return false; // Unable to create temporary file
  }

  // Iterate through each line in the file
  while (($line = fgets($file_handle)) !== false) {
    // Remove the string from the current line
    $modified_line = str_replace($string_to_remove, '', $line);
    // Write the modified line to the temporary file
    fwrite($temp_file_handle, $modified_line);
  }

  // Close the file handles
  fclose($file_handle);
  fclose($temp_file_handle);

  // Replace the original file with the modified content
  if (!rename($temp_file_path, $file_path)) {
    // Failed to rename temporary file to original file path
    unlink($temp_file_path); // Delete the temporary file
    return false;
  }

  return true;
}

function sanitizeFilename($filename)
{
  // Remove any character that is not alphanumeric, underscore, dash, or period
  $filename = preg_replace("/[^\w\-\. ]/", '-', $filename);

  return $filename;
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
    if (is_string($ip))
      $ips[] = trim($ip);
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

  return array(
      'start' => inet_ntop($range_start),
      'end' => inet_ntop($range_end)
  );
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
  $ips = array();

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
 * Fixes a text file containing NUL characters by removing them.
 *
 * @param string $inputFile The path to the input file.
 * @return void
 */
function fixFile(string $inputFile): void
{
  // Read input file and remove NUL characters
  $cleanedContent = str_replace("\x00", '', read_file($inputFile));

  // Write cleaned content back to input file
  file_put_contents($inputFile, $cleanedContent);
}

/**
 * Reads a file as UTF-8 encoded text.
 *
 * @param string $inputFile The path to the input file.
 * @return string|false The content of the file or false on failure.
 */
function read_file(string $inputFile)
{
  return file_get_contents($inputFile, false, stream_context_create(['http' => ['header' => 'Content-Type: text/plain; charset=UTF-8']]));
}

/**
 * Get a random file from a folder.
 *
 * @param string $folder The path to the folder containing files.
 * @param string|null $file_extension The optional file extension without dot (.) to filter files by.
 * @return string|null The name of the randomly selected file, or null if no file found with the specified extension.
 */
function getRandomFileFromFolder($folder, $file_extension = null): ?string
{
  // Get list of files in the folder
  $files = scandir($folder);

  // Remove special directories "." and ".." from the list
  $files = array_diff($files, array('.', '..'));

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
  $connection = @fsockopen($ip, $port, $errno, $errstr, 10);
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
  $inputHandle = fopen($inputFile, 'r+');

  // Create a temporary file for storing unique lines
  $tempFile = tmpfile();

  // Copy unique lines to the temporary file
  while (($line = fgets($inputHandle)) !== false) {
    $line = trim($line);
    if (!empty($line)) {
      // Check if line is unique
      if (strpos(stream_get_contents($tempFile), $line) === false) {
        fwrite($tempFile, $line . PHP_EOL);
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
  file_put_contents($filePath, ''); // Write an empty string to truncate the file
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
 * @param string $filename The path to the file.
 * @param int $chunkSize Optional. The size of each chunk to read in bytes. Defaults to 4096.
 * @return int|false The number of non-empty lines in the file, or false if the file couldn't be opened.
 */
function countNonEmptyLines($filename, $chunkSize = 4096)
{
  $file = fopen($filename, "r");
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
