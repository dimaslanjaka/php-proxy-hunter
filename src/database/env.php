<?php

// Load dotenv if not already loaded
if (class_exists('Dotenv\\Dotenv')) {
  if (!isset($_ENV['DEBUG_DEVICES']) && !getenv('DEBUG_DEVICES') && !isset($_ENV['ADMIN_EMAILS']) && !getenv('ADMIN_EMAILS')) {
    $projectRoot = realpath(__DIR__ . '/../../');
    if ($projectRoot === false) {
      throw new RuntimeException('Could not determine project root directory.');
    }
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable($projectRoot);
    $dotenv->load();
    // For compatibility with libraries using getenv()
    foreach ($_ENV as $key => $value) {
      putenv("$key=$value");
    }
  }
}

/**
 * Retrieves the list of admin email addresses from the environment variable 'ADMIN_EMAILS'.
 *
 * @return string[] Array of trimmed admin email addresses.
 */
function getAdminEmails(): array
{
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
function is_debug_device()
{
  $debug_devices_env = isset($_ENV['DEBUG_DEVICES']) ? $_ENV['DEBUG_DEVICES'] : getenv('DEBUG_DEVICES');
  if (empty($debug_devices_env)) {
    error_log('DEBUG_DEVICES environment variable is not set or empty.');
    return false;
  }
  $debug_devices = array_map('trim', explode(',', $debug_devices_env));
  $hostname      = gethostname();
  return in_array($hostname, $debug_devices, true);
}
