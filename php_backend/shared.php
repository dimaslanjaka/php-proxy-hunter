<?php

require_once __DIR__ . '/../func-proxy.php';

use PhpProxyHunter\CoreDB;

// Disallow access to this file directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
  http_response_code(403);
  exit('Direct access not permitted.');
}

// Always attempt to load .env from project root if Dotenv is available.
if (class_exists('Dotenv\\Dotenv')) {
  try {
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable(dirname(__DIR__, 1));
    $dotenv->load();
    // For compatibility with libraries using getenv()
    foreach ($_ENV as $key => $value) {
      putenv("$key=$value");
    }
  } catch (Dotenv\Exception\InvalidPathException $e) {
    // .env not found — continue with environment variables from server/CI.
    if (is_cli()) {
      echo '[dotenv] .env file not found, skipping load.' . PHP_EOL;
    }
  } catch (Throwable $e) {
    // Any other dotenv-related error should not be fatal for scripts.
    // Log to stdout for CI visibility.
    if (is_cli()) {
      echo '[dotenv] failed to load: ' . $e->getMessage() . PHP_EOL;
    }
  }
}

// Declare a database connection variable
$dbName  = is_debug() ? 'php_proxy_hunter_test' : ($_ENV['MYSQL_DBNAME'] ?? getenv('MYSQL_DBNAME'));
$dbUser  = is_debug_device() ? ($_ENV['MYSQL_USER'] ?? getenv('MYSQL_USER')) : ($_ENV['MYSQL_USER_PRODUCTION'] ?? getenv('MYSQL_USER_PRODUCTION'));
$dbPass  = is_debug_device() ? ($_ENV['MYSQL_PASS'] ?? getenv('MYSQL_PASS')) : ($_ENV['MYSQL_PASS_PRODUCTION'] ?? getenv('MYSQL_PASS_PRODUCTION'));
$dbHost  = is_debug_device() ? ($_ENV['MYSQL_HOST'] ?? getenv('MYSQL_HOST')) : ($_ENV['MYSQL_HOST_PRODUCTION'] ?? getenv('MYSQL_HOST_PRODUCTION'));
$dbFile  = is_debug() ? __DIR__ . '/../tmp/database_test.sqlite' : __DIR__ . '/../src/database.sqlite';
$dbType  = is_debug() ? 'sqlite' : 'mysql';
$core_db = new CoreDB(
  $dbFile,
  $dbHost,
  $dbName,
  $dbUser,
  $dbPass,
  false,
  $dbType
);
/** @var \PhpProxyHunter\UserDB $user_db */
$user_db = $core_db->user_db;
/** @var \PhpProxyHunter\ProxyDB $proxy_db */
$proxy_db = $core_db->proxy_db;
/** @var \PhpProxyHunter\ActivityLog $log_db */
$log_db = $core_db->log_db;

/**
 * Get current authenticated user data from the database.
 *
 * @return array|null Returns user data array if found, null otherwise
 */
function getCurrentUserData()
{
  global $user_db;
  $email = !is_cli() ? ($_SESSION['authenticated_email'] ?? '') : '';
  $user  = $user_db->select($email);
  return !empty($user) ? $user : null;
}
