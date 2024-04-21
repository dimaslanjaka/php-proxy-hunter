<?php

define('PHP_PROXY_HUNTER', '');
require_once __DIR__ . '/vendor/autoload.php';

use PhpProxyHunter\ProxyDB;

$db = new ProxyDB();

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

// create temp folder
if (!file_exists(__DIR__ . '/tmp')) mkdir(__DIR__ . '/tmp');

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
    if (file_exists($filename) && is_readable($filename) && is_writable($filename)) {
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
  if (!is_writable($sourceFilePath) && !is_writable($destinationFilePath)) return false;

  // Read content from the source file
  $sourceContent = file_get_contents($sourceFilePath);

  if (strpos($sourceContent, $stringToRemove) === false) return false;

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
 * @param string $proxyType The type of proxy. Can be 'http', 'socks4', or 'socks5'. Defaults to 'http'.
 * @param int $cacheTime The cache expiration time in seconds. Set to 0 to disable caching. Defaults to 1 year (86400 * 360 seconds).
 * @param string $cacheDir The directory where cached responses will be stored. Defaults to './.cache/' in the current directory.
 * @return string|false The response content or false on failure.
 */
function curlGetWithProxy($url, $proxy, $proxyType = 'http', $cacheTime = 86400 * 360, $cacheDir = __DIR__ . '/.cache/')
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
  $type = CURLPROXY_HTTP;
  if ($proxyType == 'socks4') $type = CURLPROXY_SOCKS4;
  if ($proxyType == 'socks5') $type = CURLPROXY_SOCKS5;
  curl_setopt($ch, CURLOPT_PROXYTYPE, $type);

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

  if (file_exists($filename) && is_readable($filename) && is_writable($filename)) {
    // Open the file for reading
    $file = fopen($filename, "r");

    if (is_resource($file)) {
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

      if (is_resource($file)) {
        // Write extracted IP:PORT combinations to the file
        foreach (array_unique($ipPortList) as $ipPort) {
          fwrite($file, $ipPort . "\n");
        }

        // Close the file
        fclose($file);
      }
    }
  }

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

/**
 * Extracts IP:PORT combinations from a file.
 *
 * @param string $filePath The path to the file containing IP:PORT combinations.
 * @param bool $unique (Optional) If set to true, returns only unique IP:PORT combinations. Default is false.
 * @return array An array containing the extracted IP:PORT combinations.
 */
function extractIpPortFromFile($filePath, bool $unique = false)
{
  $ipPortList = array();

  if (file_exists($filePath)) {
    // Open the file for reading
    $fp = fopen($filePath, "r");
    if (!$fp) {
      throw new Exception('File open failed.');
    }

    if ($fp != false) {
      // Read each line from the file
      while (!feof($fp)) {
        $line = fgets($fp);

        // Match IP:PORT pattern using regular expression
        preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+)/', $line, $matches);

        // Add matched IP:PORT combinations to the list
        foreach ($matches[0] as $match) {
          $ipPortList[] = trim($match);
        }
      }

      // Close the file
      fclose($fp);
    }
  }

  if ($unique) return array_unique($ipPortList);
  return $ipPortList;
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

function setConfig($user_id, $data)
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

function generateRandomString($length = 10)
{
  $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[rand(0, strlen($characters) - 1)];
  }
  return $randomString;
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
  if (file_exists($filePath) && is_readable($filePath) && is_writable($filePath)) {
    // Read the file into an array, each line as an element
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (is_array($lines)) {
      // Remove duplicate lines
      $lines = array_unique($lines);

      // Remove empty strings from the array
      $lines = array_filter($lines, function ($value) {
        return trim($value) !== '';
      });
      // Write the modified lines back to the file
      file_put_contents($filePath, implode("\n", $lines) . "\n");
    }
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
function mergeArrays(array $arr1, array $arr2)
{
  $keys = array_keys($arr2);
  foreach ($keys as $key) {
    if (isset($arr1[$key]) && is_numeric($key)) {
      array_push($arr1, $arr2[$key]);
    } else if (isset($arr1[$key]) && is_array($arr1[$key]) && is_array($arr2[$key])) {
      $arr1[$key] = array_unique(mergeArrays((array)$arr1[$key], (array) $arr2[$key]));
    } else {
      $arr1[$key] = $arr2[$key];
    }
  }
  return $arr1;
}

/**
 * Check if a port is open on a given IP address.
 *
 * @param string $proxy The IP address and port to check in the format "IP:port".
 * @param int $timeout The timeout value in seconds (default is 10 seconds).
 * @return bool True if the port is open, false otherwise.
 */
function isPortOpen(string $proxy, int $timeout = 10)
{
  // Separate IP and port
  list($ip, $port) = explode(':', trim($proxy));

  // Create a TCP/IP socket with the specified timeout
  $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

  // Check if the socket could be opened
  if ($socket === false) {
    return false; // Port is closed
  } else {
    fclose($socket);
    return true; // Port is open
  }
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

  // Read the file into an array of lines
  $lines = file($filePath);

  // Check if the file can be written
  if (!is_writable($filePath)) {
    // echo "Error: The file '$filePath' is not writable.";
    return;
  }

  // Filter out empty lines
  $lines = array_filter($lines, function ($line) {
    // Remove leading and trailing whitespace from the line and check if it's empty
    return trim($line) !== '';
  });

  // Rewrite the non-empty lines back to the file
  file_put_contents($filePath, implode('', $lines));

  // echo "Empty lines removed successfully from $filePath.";
}

/**
 * Move content from a source file to a destination file in append mode.
 *
 * @param string $sourceFile      The path to the source file.
 * @param string $destinationFile The path to the destination file.
 *
 * @return bool True if the content was moved successfully, false otherwise.
 */
function moveContent($sourceFile, $destinationFile)
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
 * @param string $sourceFile      Path to the source file.
 * @param string $destinationFile Path to the destination file.
 * @param int    $linesToMove     Number of lines to move.
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
  // Open the file for appending
  $handle = fopen($file, 'a');

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
function removeStringFromFile($file_path, $string_to_remove)
{
  // Read the file
  $file_content = file_get_contents($file_path);

  // Remove the string
  $new_content = str_replace($string_to_remove, '', $file_content);

  // Write the modified content back to the file
  $result = file_put_contents($file_path, $new_content);

  return $result !== false;
}

function sanitizeFilename($filename)
{
  // Remove any character that is not alphanumeric, underscore, dash, or period
  $filename = preg_replace("/[^\w\-\. ]/", '-', $filename);

  return $filename;
}

function getIPRange(string $cidr)
{
  list($ip, $mask) = explode('/', trim($cidr));

  $ipLong = ip2long($ip);
  $maskLong = ~((1 << (32 - $mask)) - 1);

  $start = $ipLong & $maskLong;
  $end = $ipLong | (~$maskLong & 0xFFFFFFFF);

  $ips = array();
  for ($i = $start; $i <= $end; $i++) {
    $ips[] = long2ip($i);
  }

  return $ips;
}

// Example usage
// $cidr = "159.21.130.0/24";
// $ipList = getIPRange($cidr);

// foreach ($ipList as $ip) {
//   echo $ip . "\n";
// }

function IPv6CIDRToRange($cidr)
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

function IPv6CIDRToList($cidr)
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
 * Generates a random Android user-agent string.
 *
 * @param string $type The type of browser user-agent to generate. Default is 'chrome'.
 * @return string The generated user-agent string.
 */
function randomAndroidUa(string $type = 'chrome')
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
    'Samsung' => ['Galaxy S20', 'Galaxy Note 10', 'Galaxy A51', 'Galaxy S10', 'Galaxy S9', 'Galaxy Note 9', 'Galaxy S21', 'Galaxy Note 20', 'Galaxy Z Fold 2', 'Galaxy A71', 'Galaxy S20 FE'],
    'Google' => ['Pixel 4', 'Pixel 3a', 'Pixel 3', 'Pixel 5', 'Pixel 4a', 'Pixel 4 XL', 'Pixel 3 XL'],
    'Huawei' => ['P30 Pro', 'Mate 30', 'P40', 'Mate 40 Pro', 'P40 Pro', 'Mate Xs', 'Nova 7i'],
    'Xiaomi' => ['Mi 10', 'Redmi Note 9', 'POCO F2 Pro', 'Mi 11', 'Redmi Note 10 Pro', 'POCO X3', 'Mi 10T Pro', 'Redmi Note 4x', 'Redmi Note 5', 'Redmi 6a', 'Mi 8 Lite'],
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
function randomIosUa(string $type = 'chrome')
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
