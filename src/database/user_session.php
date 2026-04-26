<?php

if (!function_exists('write_file')) {
  require_once __DIR__ . '/../utils/file/crud.php';
}
if (!function_exists('setMultiPermissions')) {
  require_once __DIR__ . '/../utils/file/permissions.php';
}
if (!function_exists('parseQueryOrPostBody')) {
  require_once __DIR__ . '/../utils/server/postdata.php';
}
if (!function_exists('get_project_root')) {
  require_once __DIR__ . '/../PhpProxyHunter/utils/get_project_root.php';
}

$isCli = php_sapi_name() === 'cli';

// Default user ID to "CLI" assuming the script is running from the command line
$user_id = 'CLI';

// Check if the script is running in CLI mode or not
if (!$isCli) {
  // --- Case 1: Running via web server ---

  // Prioritize email → then user_id → then session_id
  if (isset($_SESSION['email']) && isValidEmail($_SESSION['email'])) {
    // Use valid session email first
    $user_id_source = $_SESSION['email'];
  } elseif (!empty($_SESSION['user_id'])) {
    // Use session user ID if available
    $user_id_source = $_SESSION['user_id'];
  } else {
    // Fallback: use PHP session ID
    $user_id_source = session_id();
  }

  // Hash the chosen ID for privacy and consistency
  $user_id = md5($user_id_source);
} else {
  // --- Case 2: Running in CLI mode ---

  // Parse command-line arguments using a helper function
  $parsedArgs = parseQueryOrPostBody();

  // Extract CLI user ID if provided (userId or uid)
  $cliUserId = '';
  if (!empty($parsedArgs)) {
    if (isset($parsedArgs['userId'])) {
      $cliUserId = $parsedArgs['userId'];
    } elseif (isset($parsedArgs['uid'])) {
      $cliUserId = $parsedArgs['uid'];
    }
  }

  // If CLI user ID is non-empty, override default "CLI"
  if (!empty(trim($cliUserId))) {
    $user_id = $cliUserId;
  }
}

/**
 * Set the current user ID and create a user config file if it doesn't exist.
 *
 * @param string $new_user_id The new user ID to be set.
 *
 * This function sets the global user ID, ensures the user config file exists,
 * and writes default config if the file is not already present.
 */
function setUserId(string $new_user_id) {
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

// Set the resolved user ID in your system
setUserId($user_id);

/**
 * Retrieve the current global user ID.
 *
 * @return string The current user ID.
 */
function getUserId(): string {
  global $user_id;
  return $user_id;
}

// Ensure config directory exists and has proper permissions
$__pph_root = get_project_root();
if (!file_exists($__pph_root . '/config')) {
  mkdir($__pph_root . '/config');
}
setMultiPermissions($__pph_root . '/config');

function getUserFile(string $user_id): string {
  $root = get_project_root();
  return $root . "/config/$user_id.json";
}

function getUserStatusFile(string $user_id): string {
  $root = get_project_root();
  return $root . "/tmp/status/$user_id.txt";
}

function getUserLogFile(string $user_id): string {
  $root = get_project_root();
  return $root . "/tmp/logs/$user_id.txt";
}

function resetUserLogFile(string $user_id): bool {
  $user_file = getUserLogFile($user_id);
  $now       = date('Y-m-d H:i:s');
  $content   = "Log reset at $now\n";
  return file_put_contents($user_file, $content, LOCK_EX) !== false;
}

function addUserLog(string $user_id, string $message): bool {
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


function getConfig(string $user_id): array {
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
  $data = json_decode($jsonString, true);
  // Use true for associative array, false or omit for object

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

function setConfig($user_id, $data): array {
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
