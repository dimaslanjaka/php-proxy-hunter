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

/**
 * Set the current user ID and create a user config file if it doesn't exist.
 *
 * @param string $new_user_id The new user ID to be set.
 *
 * @return void
 */
function setUserId(string $new_user_id) {
  global $user_id;

  $user_file = !empty($new_user_id)
      ? getUserFile($new_user_id)
      : null;

  if ($user_file !== null) {
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
        'endpoint' => $new_user_id === 'CLI'
            ? 'https://api.myxl.xlaxiata.co.id/api/v1/xl-stores/options/list'
            : 'https://bing.com',

        'headers' => $new_user_id === 'CLI'
            ? $headers
            : [
              'User-Agent: Mozilla/5.0 (Linux; Android 14; Pixel 6 Pro Build/UPB3.230519.014) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.60 Mobile Safari/537.36 GNews Android/2022137898',
            ],

        'type' => 'http|socks4|socks5',
      ];

      $file = getUserFile($new_user_id);

      write_file($file, json_encode($data));
    }

    // Replace global user ID if different from current
    if (!isset($user_id) || $user_id !== $new_user_id) {
      $user_id = $new_user_id;
    }
  }
}

/**
 * Retrieve the current global user ID.
 *
 * @return string
 */
function getUserId(): string {
  // Default user ID for CLI mode
  $user_id = 'CLI';

  // Check if running from web server
  if (!is_cli()) {
    // Prioritize email → user_id → session_id
    if (
      isset($_SESSION['email']) && isValidEmail($_SESSION['email'])
    ) {
      $user_id_source = $_SESSION['email'];
    } elseif (!empty($_SESSION['user_id'])) {
      $user_id_source = $_SESSION['user_id'];
    } else {
      $user_id_source = session_id();
    }

    // Hash for privacy/consistency
    $user_id = md5($user_id_source);
  } else {
    // CLI mode
    $parsedArgs = parseQueryOrPostBody();

    $cliUserId = '';

    if (!empty($parsedArgs)) {
      if (isset($parsedArgs['userId'])) {
        $cliUserId = $parsedArgs['userId'];
      } elseif (isset($parsedArgs['uid'])) {
        $cliUserId = $parsedArgs['uid'];
      }
    }

    // Override default CLI user
    if (!empty(trim($cliUserId))) {
      $user_id = $cliUserId;
    }
  }

  return $user_id;
}

// Ensure config directory exists and has proper permissions
$configDir = get_project_root('tmp/config');

if (!file_exists($configDir)) {
  mkdir($configDir, 0777, true);
}

setMultiPermissions($configDir);

/**
 * Get user config file path.
 *
 * @param string $user_id
 *
 * @return string
 */
function getUserFile(string $user_id): string {
  return get_project_root('tmp/config', $user_id . '.json');
}

/**
 * Get user status file path.
 *
 * @param string $user_id
 *
 * @return string
 */
function getUserStatusFile(string $user_id): string {
  return get_project_root('tmp', 'status', $user_id . '.txt');
}

/**
 * Get user log file path.
 *
 * @param string $user_id
 *
 * @return string
 */
function getUserLogFile(string $user_id): string {
  return get_project_root('tmp', 'logs', $user_id . '.txt');
}

/**
 * Reset user log file.
 *
 * @param string $user_id
 *
 * @return bool
 */
function resetUserLogFile(string $user_id): bool {
  $user_file = getUserLogFile($user_id);

  $now = date('Y-m-d H:i:s');

  $content = "Log reset at {$now}\n";

  return file_put_contents(
    $user_file,
    $content,
    LOCK_EX
  ) !== false;
}

/**
 * Add line into user log file.
 *
 * @param string $user_id
 * @param string $message
 *
 * @return bool
 */
function addUserLog(string $user_id, string $message): bool {
  $user_file = getUserLogFile($user_id);

  if (!file_exists(dirname($user_file))) {
    mkdir(dirname($user_file), 0777, true);
  }

  if (!file_exists($user_file)) {
    $now = date('Y-m-d H:i:s');

    $header = "Log created at {$now}\n";

    file_put_contents(
      $user_file,
      $header,
      LOCK_EX
    );
  }

  return file_put_contents(
    $user_file,
    date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL,
    FILE_APPEND | LOCK_EX
  ) !== false;
}

/**
 * Read user config.
 *
 * @param string $user_id
 *
 * @return array
 */
function getConfig(string $user_id): array {
  $user_file = getUserFile($user_id);

  if (!file_exists($user_file)) {
    setUserId($user_id);

    $user_file = getUserFile($user_id);
  }

  if (!is_readable($user_file)) {
    setMultiPermissions($user_file, false);
  }

  // Read JSON file
  $jsonString = read_file($user_file);

  // Decode JSON
  $data = json_decode($jsonString, true);

  $defaults = [
    'endpoint' => 'https://google.com',
    'headers'  => [],
    'type'     => 'http|socks4|socks5',
    'user_id'  => $user_id,
  ];

  // Invalid JSON
  if (
    $data === null && json_last_error() !== JSON_ERROR_NONE
  ) {
    return $defaults;
  }

  return mergeArrays($defaults, $data);
}

/**
 * Update and save user configuration.
 *
 * @param string $user_id
 * @param array  $data
 *
 * @return array
 */
function setConfig(string $user_id, array $data): array {
  $user_file = getUserFile($user_id);

  // Existing/default config
  $defaults = getConfig($user_id);

  // Remove conflicting fields
  unset($defaults['headers']);

  // Merge configs
  $newConfig = mergeArrays($defaults, $data);

  // Encode JSON safely
  $json = json_encode(
    $newConfig,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
  );

  if ($json === false) {
    return $defaults;
  }

  // Save config
  file_put_contents(
    $user_file,
    $json,
    LOCK_EX
  );

  // Fix permissions
  setMultiPermissions($user_file);

  return $newConfig;
}
