<?php

include_once __DIR__ . '/../utils/shim/string.php';

$pairs = [];

// Load dotenv if not already loaded
if (class_exists('Dotenv\\Dotenv')) {
  $projectRoot = realpath(__DIR__ . '/../../');
  if ($projectRoot === false) {
    throw new RuntimeException('Could not determine project root directory.');
  }
  $dotenv = Dotenv\Dotenv::createUnsafeImmutable($projectRoot);
  $dotenv->load();
  // For compatibility with libraries using getenv()
  foreach ($_ENV as $key => $value) {
    putenv("$key=$value");
    $pairs[$key] = $value;
  }
} else {
  // Parse .env file manually if Dotenv is not available
  $envFile = __DIR__ . '/../../.env';
  if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      if (str_starts_with(trim($line), '#')) {
        continue;
        // Skip comments
      }
      $parts = explode('=', $line, 2);
      if (count($parts) === 2) {
        $key   = trim($parts[0]);
        $value = trim($parts[1]);

        // Always strip surrounding quotes (single or double)
        $value = trim($value, '"\'');

        if ($value === 'true' || $value === 'false') {
          $value = $value === 'true' ? true : false;
        } elseif (is_numeric($value)) {
          $value = $value + 0;
        // Convert to int or float
        } elseif (preg_match('/^\[.*\]$/', $value)) {
          $arrayValue = json_decode($value, true);
          if (json_last_error() === JSON_ERROR_NONE) {
            $value = $arrayValue;
          }
        }
        $_ENV[$key] = $value;
        putenv("$key=$value");
        $pairs[$key] = $value;
      }
    }
  }
}

/**
 * Retrieves the global environment variable pairs loaded from .env file or Dotenv.
 *
 * @return array<string, mixed> An associative array of environment variable key-value pairs.
 */
function getEnvPairs(): array {
  global $pairs;
  return $pairs;
}

/**
 * Retrieves the list of admin email addresses from the environment variable 'ADMIN_EMAILS'.
 *
 * @return string[] Array of trimmed admin email addresses.
 */
function getAdminEmails(): array {
  $email       = isset($_ENV['ADMIN_EMAILS']) ? $_ENV['ADMIN_EMAILS'] : getenv('ADMIN_EMAILS');
  $adminEmails = $email ? explode(',', $email) : [];
  return array_map('trim', $adminEmails);
}

/**
 * Checks if the current hostname matches any of the debug devices
 * defined in the `DEBUG_DEVICES` environment variable.
 *
 * @return bool True if the hostname matches a debug device, false otherwise.
 */
function is_debug_device() {
  $debug_devices_env = isset($_ENV['DEBUG_DEVICES']) ? $_ENV['DEBUG_DEVICES'] : getenv('DEBUG_DEVICES');
  if (empty($debug_devices_env)) {
    error_log('DEBUG_DEVICES environment variable is not set or empty.');
    return false;
  }
  $debug_devices = array_map('trim', explode(',', $debug_devices_env));
  $hostname      = gethostname();
  return in_array($hostname, $debug_devices, true);
}

/**
 * Checks if the current environment is GitHub Codespaces.
 *
 * @return bool True if running in GitHub Codespaces, false otherwise.
 */
function is_github_codespaces(): bool {
  $hostname = gethostname();
  if (str_starts_with($hostname, 'codespaces-')) {
    return true;
  }
  return getenv('CODESPACES') === 'true';
}

/**
 * Checks if the current environment is GitHub CI.
 *
 * @return bool True if running in GitHub CI, false otherwise.
 */
function is_github_ci(): bool {
  return getenv('CI') !== false && getenv('GITHUB_ACTIONS') === 'true';
}

/**
 * Determines whether the application is in debug mode. (DEVELOPMENT MODE)
 *
 * Debug mode is activated based on several conditions:
 * - If running in a GitHub CI environment.
 * - If running in GitHub Codespaces.
 * - If the hostname matches a debug device from the `DEBUG_DEVICES` variable.
 * - If the hostname starts with 'codespaces-'.
 *
 * @return bool True if in debug mode, false otherwise.
 */
function is_debug(): bool {
  return is_github_ci()
    || is_github_codespaces()
    || is_debug_device();
}

/**
 * Detect whether the current execution environment is the command line (CLI).
 *
 * This function uses several checks to be robust across environments:
 * - php_sapi_name() === 'cli'
 * - existence of the STDIN constant
 * - absent web server variables and presence of argv
 *
 * @return bool True when running via CLI, false otherwise.
 */
function is_cli(): bool {
  return (
    php_sapi_name() === 'cli'
    || defined('STDIN')
    || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && (!empty($_SERVER['argv']) && count($_SERVER['argv']) > 0))
  );
}

/**
 * Determine whether the current request/context has administrative privileges.
 *
 * Behavior differs depending on execution environment:
 * - CLI: looks for an --admin option in command-line arguments.
 * - Web: checks for a truthy `$_SESSION['admin']` and requires an active session.
 *
 * @throws RuntimeException If called in web context and no session has been started.
 * @return bool True when admin privileges are present, false otherwise.
 */
function is_admin(): bool {
  if (is_cli()) {
    $options = getopt('', ['admin']);
    return isset($options['admin']);
  }

  // Web context
  if (session_status() !== PHP_SESSION_NONE) {
    return !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
  }

  throw new RuntimeException('Session has not been started.');
}

/**
 * Requires admin privileges; terminates with a 403 error if the user is not an admin.
 *
 * @return void Exits with code 1 if not an admin; returns normally if admin privileges are present.
 * @throws RuntimeException May throw if is_admin() throws in web context without active session.
 */
function requires_admin() {
  if (!is_admin()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => true, 'message' => 'Admin privileges required.']);
    exit(1);
  }
}

/**
 * Checks if the current user is authenticated.
 *
 * Behavior depends on execution context:
 * - CLI: always returns true.
 * - Web: checks for valid user_id and email in $_SESSION.
 *
 * @throws RuntimeException If called in web context without an active session.
 * @return bool True if authenticated, false otherwise.
 */
function is_authenticated(): bool {
  if (is_cli()) {
    return true;
  }

  if (session_status() === PHP_SESSION_NONE) {
    throw new RuntimeException('[is_authenticated] Session has not been started.');
  }

  return !empty($_SESSION['user_id']) && !empty($_SESSION['email']);
}

/**
 * Requires authentication; terminates with a 401 error if the user is not authenticated.
 *
 * @return void Exits with code 1 if not authenticated; returns normally if authenticated.
 * @throws RuntimeException May throw if is_authenticated() throws in web context without active session.
 */
function requires_authentication() {
  if (!is_authenticated()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => true, 'message' => 'Authentication required.']);
    exit(1);
  }
}

/** Check if the captcha is verified */
function is_captcha_verified(): bool {
  if (is_cli()) {
    return true;
  }

  if (session_status() === PHP_SESSION_NONE) {
    throw new RuntimeException('[is_captcha_verified] Session has not been started.');
  }

  return !empty($_SESSION['captcha']) && $_SESSION['captcha'] === true;
}

/** Requires captcha verification */
function requires_captcha_verification() {
  if (!is_captcha_verified()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => true, 'message' => 'Captcha verification required.']);
    exit(1);
  }
}
