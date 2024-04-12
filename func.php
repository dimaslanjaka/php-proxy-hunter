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

/**
 * Function to extract IP:PORT combinations from a text file and rewrite the file with only IP:PORT combinations.
 *
 * @param string $filename The path to the text file.
 * @return void
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
        'headers' => $headers
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
  if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    // Decoding failed
    // echo 'Error decoding JSON: ' . json_last_error_msg();
  } else {
    return $data;
  }
}

function setConfig($user_id, $data)
{
  $user_file = getUserFile($user_id);
  $data['user_id'] = getUserId();
  // Encode the data to JSON format
  $jsonData = json_encode($data);
  // write data
  file_put_contents($user_file, $jsonData);
  // set permission
  setFilePermissions($user_file);
}
