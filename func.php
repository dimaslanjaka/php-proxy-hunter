<?php

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

// debug all errors
error_reporting(E_ALL);
ini_set('display_errors', '1');

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
if (!$isCli) session_start();

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
function setPermissions($filename)
{
  try {
    if (file_exists($filename)) {
      if (chmod($filename, 0777)) {
        // echo "File permissions set to 777 for $filename successfully.\n";
      } else {
        // echo "Failed to set file permissions for $filename.\n";
      }
    } else {
      // echo "File $filename does not exist.\n";
    }
  } catch (\Throwable $th) {
    //throw $th;
  }
}

function removeStringAndMoveToFile($sourceFilePath, $destinationFilePath, $stringToRemove)
{
  // Read content from the source file
  $sourceContent = file_get_contents($sourceFilePath);

  // Remove the desired string
  $modifiedContent = str_replace($stringToRemove, '', $sourceContent);

  // Write the modified content back to the source file
  $writeSrc = file_put_contents($sourceFilePath, $modifiedContent);

  // Append the removed string to the destination file
  $writeDest = file_put_contents($destinationFilePath,  PHP_EOL . $stringToRemove . PHP_EOL, FILE_APPEND);

  return $writeSrc != false && $writeDest != false;
}

/**
 * get cache file from `curlGetWithProxy`
 */
function curlGetCache($url)
{
  return __DIR__ . '/.cache/' . md5($url);
}

/**
 * Fetches the content of a URL using cURL with a specified proxy, with caching support.
 *
 * @param string $url The URL to fetch.
 * @param string $proxy The proxy IP address and port (e.g., "proxy_ip:proxy_port").
 * @param int $cacheTime The cache expiration time in seconds. Set to 0 to disable caching.
 * @param string $cacheDir The directory where cached responses will be stored.
 * @return string|false The response content or false on failure.
 */
function curlGetWithProxy($url, $proxy, $cacheTime = 86400 * 360, $cacheDir = __DIR__ . '/.cache/')
{
  // Generate cache file path based on URL
  if (!file_exists($cacheDir)) mkdir($cacheDir);
  $cacheFile = $cacheDir . md5($url);

  // Check if cached data exists and is still valid
  if ($cacheTime > 0 && file_exists($cacheFile) && time() - filemtime($cacheFile) < $cacheTime) {
    // Return cached response
    return file_get_contents($cacheFile);
  }

  // Initialize cURL session
  $ch = curl_init();

  // Set the URL
  curl_setopt($ch, CURLOPT_URL, $url);

  // Set proxy details
  curl_setopt($ch, CURLOPT_PROXY, $proxy);
  curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); // Change if using a different type of proxy

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
 * @return array
 */
function rewriteIpPortFile($filename)
{
  $ipPortList = array();

  // Open the file for reading
  $file = fopen($filename, "r");

  // Read each line from the file and extract IP:PORT combinations
  while (!feof($file)) {
    $line = fgets($file);

    // Match IP:PORT pattern using regular expression
    preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+)/', $line, $matches);

    // Add matched IP:PORT combinations to the list
    foreach ($matches[0] as $match) {
      $ipPortList[] = $match;
    }
  }

  // Close the file
  fclose($file);

  // Open the file for writing (truncate existing content)
  $file = fopen($filename, "w");

  // Write extracted IP:PORT combinations to the file
  foreach (array_unique($ipPortList) as $ipPort) {
    fwrite($file, $ipPort . "\n");
  }

  // Close the file
  fclose($file);

  return $ipPortList;
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

// Function to parse command line arguments
function parseArgs($args)
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
    if (!file_exists(dirname($user_file))) mkdir(dirname($user_file), 0777, true);
    // write default user config
    if (!file_exists($user_file)) {
      $headers = array(
        'X-Dynatrace: MT_3_6_2809532683_30-0_24d94a15-af8c-49e7-96a0-1ddb48909564_0_1_619',
        'X-Api-Key: vT8tINqHaOxXbGE7eOWAhA==',
        'Authorization: Bearer ', // Place your bearer token here
        'X-Request-Id: 63337f4c-ec03-4eb8-8caa-4b7cd66337e3',
        'X-Request-At: 2024-04-07T20:57:14.73+07:00',
        'X-Version-App: 5.8.8',
        'User-Agent: myXL / 5.8.8(741); StandAloneInstall; (samsung; SM-G955N; SDK 25; Android 7.1.2)',
        'Content-Type: application/json; charset=utf-8'
      );
      $data = array(
        'endpoint' => 'https://api.myxl.xlaxiata.co.id/api/v1/xl-stores/options/list',
        'headers' => $headers,
        'type' => 'http'
      );
      setConfig($new_user_id, $data);
    }
    // replace global user id
    if ($user_id != $new_user_id) $user_id = $new_user_id;
  }
}

function getUserId()
{
  global $user_id;
  return $user_id;
}

if (!file_exists(__DIR__ . "/config")) {
  mkdir(__DIR__ . "/config");
}
setFilePermissions(__DIR__ . "/config");
function getUserFile(string $user_id)
{
  return __DIR__ . "/config/$user_id.json";
}

function getConfig(string $user_id)
{
  $user_file = getUserFile($user_id);
  if (!file_exists($user_file)) {
    setUserId($user_id);
    $user_file = getUserFile($user_id);
  }
  // Read the JSON file into a string
  $jsonString = file_get_contents($user_file);

  // Decode the JSON string into a PHP array
  $data = json_decode($jsonString, true); // Use true for associative array, false or omit for object

  // Check if decoding was successful
  $defaults = array(
    'endpoint' => 'https://api.myxl.xlaxiata.co.id/api/v1/xl-stores/options/list',
    'headers' => [],
    'type' => 'http'
  );
  if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    // Decoding failed
    // echo 'Error decoding JSON: ' . json_last_error_msg();
    return $defaults;
  } else {
    return mergeArrays($defaults, $data);
  }
}

function setConfig($user_id, $data)
{
  $user_file = getUserFile($user_id);
  $data['user_id'] = getUserId();
  $defaults = getConfig($user_id);
  // Encode the data to JSON format
  $newData = json_encode(mergeArrays($defaults, $data));
  // write data
  file_put_contents($user_file, $newData);
  // set permission
  setFilePermissions($user_file);
}

function getRandomItemFromArray($array)
{
  // Get a random key from the array
  $randomKey = array_rand($array);

  // Use the random key to get the random item
  $randomItem = $array[$randomKey];

  // Return the random item
  return $randomItem;
}

/**
 * remove duplicate lines from file
 */
function removeDuplicateLines($filePath)
{
  // Read the file into an array, each line as an element
  $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

  // Remove duplicate lines
  $lines = array_unique($lines);

  // Remove empty strings from the array
  $lines = array_filter($lines, function ($value) {
    return trim($value) !== '';
  });

  // Write the modified lines back to the file
  file_put_contents($filePath, implode("\n", $lines) . "\n");
}

/**
 * Merges two shallow multidimensional arrays.
 *
 * This function merges two multidimensional arrays while preserving the structure.
 * If a key exists in both arrays, sub-arrays are merged recursively.
 * Values from the second array override those from the first array if they have the same keys.
 * If a key exists in the second array but not in the first one, it will be added to the merged array.
 *
 * @param array $array1 The first array to merge.
 * @param array $array2 The second array to merge.
 * @return array The merged array.
 */
function mergeArrays($array1, $array2)
{
  $mergedArray = [];

  foreach ($array1 as $key => $value) {
    if (is_array($value) && isset($array2[$key]) && is_array($array2[$key])) {
      // Merge the sub-arrays if both keys exist in both arrays
      $mergedArray[$key] = array_merge($value, $array2[$key]);
    } else {
      // Otherwise, add the key-value pair to the merged array
      $mergedArray[$key] = $value;
    }
  }

  // Add any additional keys from the second array
  foreach ($array2 as $key => $value) {
    if (!isset($mergedArray[$key])) {
      $mergedArray[$key] = $value;
    }
  }

  return $mergedArray;
}
