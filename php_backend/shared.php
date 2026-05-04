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

try {
  try {
    $core_db = init_local_database();
  } catch (\Throwable $localDbError) {
    // If local DB initialization fails, attempt to initialize production DB as a fallback.
    error_log('[shared.php] Local DB initialization failed: ' . $localDbError->getMessage());
    if (is_cli()) {
      echo '[shared.php] Local DB initialization failed: ' . $localDbError->getMessage() . PHP_EOL;
    }
    $core_db = init_production_database();
  }
} catch (\Throwable $th) {
  // Log the error so it's visible in logs and CLI output instead of silently
  // swallowing the exception which leads to null DB helpers later.
  error_log('[shared.php] CoreDB initialization failed: ' . $th->getMessage());
  if (function_exists('is_cli') && is_cli()) {
    echo '[shared.php] CoreDB initialization failed: ' . $th->getMessage() . PHP_EOL;
    // Exit early in CLI scripts to avoid passing a broken/null $core_db
    exit(1);
  } else {
    // In web contexts, we can choose to continue with $core_db as null and
    // handle it gracefully in the application (e.g., show an error message).
    $core_db = null;
  }
}

/** @var \PhpProxyHunter\UserDB $user_db */
$user_db = $core_db->user_db ?? null;
/** @var \PhpProxyHunter\ProxyDB $proxy_db */
$proxy_db = $core_db->proxy_db ?? null;
/** @var \PhpProxyHunter\ActivityLog $log_db */
$log_db = $core_db->log_db ?? null;

/**
 * Get current authenticated user data from the database.
 *
 * @return array|null Returns user data array if found, null otherwise
 */
function getCurrentUserData() {
  global $user_db;
  $email = !is_cli() ? ($_SESSION['authenticated_email'] ?? '') : '';
  $user  = $user_db->select($email);
  return !empty($user) ? $user : null;
}

/**
 * Refresh database connections by re-instantiating CoreDB and its DB wrappers.
 *
 * This function performs the following steps:
 * - Declares the global variables that hold DB configuration and connection objects.
 * - Unsets existing connection variables ($core_db, $user_db, $proxy_db, $log_db) to
 *   ensure old connections are released.
 * - Invokes garbage collection to free resources immediately.
 * - Creates a new CoreDB instance with the current configuration and re-assigns
 *   the wrapper properties ($user_db, $proxy_db, $log_db) from the new CoreDB.
 *
 * Globals used:
 * @global string $dbFile Path to SQLite file or ignored for MySQL
 * @global string $dbHost Database host
 * @global string $dbName Database name
 * @global string $dbUser Database username
 * @global string $dbPass Database password
 * @global string $dbType Database type identifier ('sqlite' or 'mysql')
 * @global \PhpProxyHunter\CoreDB $core_db The CoreDB instance to re-create
 * @global \PhpProxyHunter\UserDB|null $user_db The user DB wrapper (may be uninitialized)
 * @global \PhpProxyHunter\ProxyDB|null $proxy_db The proxy DB wrapper (may be uninitialized)
 * @global \PhpProxyHunter\ActivityLog|null $log_db The activity log wrapper (may be uninitialized)
 * @param bool $useFallbackProduction Whether to retry with production DB credentials
 *   when MySQL returns a connection refused error (SQLSTATE[HY000] [2002]).
 *
 * @return array{
 *   core_db:\PhpProxyHunter\CoreDB,
 *   user_db:\PhpProxyHunter\UserDB,
 *   proxy_db:\PhpProxyHunter\ProxyDB,
 *   log_db:\PhpProxyHunter\ActivityLog
 * }|null Returns the newly created CoreDB instance and its DB wrappers, or null
 *   if connection setup fails.
 */
function refreshDbConnections(bool $useFallbackProduction = false): ?array {
  global $dbFile, $dbHost, $dbName, $dbUser, $dbPass, $dbType, $core_db, $user_db, $proxy_db, $log_db;

  // Clean up existing DB connections to avoid conflicts
  unset($core_db, $user_db, $proxy_db, $log_db);
  gc_collect_cycles();

  try {
    $core_db = new CoreDB(
      $dbFile,
      $dbHost,
      $dbName,
      $dbUser,
      $dbPass,
      false,
      $dbType
    );
  } catch (\Throwable $th) {
    $errorMessage        = $th->getMessage();
    $isConnectionRefused = str_contains($errorMessage, 'SQLSTATE[HY000] [2002]')
      && str_contains(strtolower($errorMessage), 'actively refused');

    if (!($useFallbackProduction && $isConnectionRefused)) {
      // Handle connection errors gracefully
      echo 'Error refreshing DB connections: ' . $errorMessage . PHP_EOL;
      return null;
    }

    // Retry with production credentials when explicitly requested.
    $productionDbName = $_ENV['MYSQL_DBNAME_PRODUCTION'] ?? getenv('MYSQL_DBNAME_PRODUCTION') ?: ($_ENV['MYSQL_DBNAME'] ?? getenv('MYSQL_DBNAME'));
    $productionDbUser = $_ENV['MYSQL_USER_PRODUCTION']   ?? getenv('MYSQL_USER_PRODUCTION') ?: ($_ENV['MYSQL_USER'] ?? getenv('MYSQL_USER'));
    $productionDbPass = $_ENV['MYSQL_PASS_PRODUCTION']   ?? getenv('MYSQL_PASS_PRODUCTION') ?: ($_ENV['MYSQL_PASS'] ?? getenv('MYSQL_PASS'));
    $productionDbHost = $_ENV['MYSQL_HOST_PRODUCTION']   ?? getenv('MYSQL_HOST_PRODUCTION') ?: ($_ENV['MYSQL_HOST'] ?? getenv('MYSQL_HOST'));

    try {
      $core_db = new CoreDB(
        $dbFile,
        $productionDbHost,
        $productionDbName,
        $productionDbUser,
        $productionDbPass,
        false,
        'mysql'
      );

      // Persist fallback credentials for subsequent reconnect attempts.
      $dbHost = $productionDbHost;
      $dbName = $productionDbName;
      $dbUser = $productionDbUser;
      $dbPass = $productionDbPass;
      $dbType = 'mysql';
    } catch (\Throwable $fallbackTh) {
      echo 'Error refreshing DB connections: ' . $fallbackTh->getMessage() . PHP_EOL;
      return null;
    }
  }
  /** @var \PhpProxyHunter\UserDB $user_db */
  $user_db = $core_db->user_db;
  /** @var \PhpProxyHunter\ProxyDB $proxy_db */
  $proxy_db = $core_db->proxy_db;
  /** @var \PhpProxyHunter\ActivityLog $log_db */
  $log_db = $core_db->log_db;

  return ['core_db' => $core_db, 'user_db' => $user_db, 'proxy_db' => $proxy_db, 'log_db' => $log_db];
}

/**
 * Initialize a production CoreDB instance using production environment
 * credentials (falls back to standard env vars when needed).
 *
 * This creates and returns a configured `CoreDB` object. Callers should
 * handle any exceptions thrown during connection initialization.
 *
 * @return \PhpProxyHunter\CoreDB
 */
function init_production_database(): CoreDB {
  $dbName = is_debug() ? 'php_proxy_hunter_test' : ($_ENV['MYSQL_DBNAME'] ?? getenv('MYSQL_DBNAME'));
  $dbUser = $_ENV['MYSQL_USER_PRODUCTION'] ?? getenv('MYSQL_USER_PRODUCTION');
  $dbPass = $_ENV['MYSQL_PASS_PRODUCTION'] ?? getenv('MYSQL_PASS_PRODUCTION');
  $dbHost = $_ENV['MYSQL_HOST_PRODUCTION'] ?? getenv('MYSQL_HOST_PRODUCTION');
  $dbFile = is_debug() ? get_project_root('tmp/database_test.sqlite') : get_project_root('src/database.sqlite');
  // Github CI uses SQLite for testing to avoid needing a MySQL service
  $dbType = is_github_ci() ? 'sqlite' : 'mysql';
  return new CoreDB(
    $dbFile,
    $dbHost,
    $dbName,
    $dbUser,
    $dbPass,
    false,
    $dbType
  );
}

/**
 * Initialize a local CoreDB instance using local environment credentials.
 *
 * This creates and returns a configured `CoreDB` object for local or
 * development usage. Callers should handle any exceptions thrown during
 * connection initialization.
 *
 * @return \PhpProxyHunter\CoreDB
 */
function init_local_database(): CoreDB {
  $dbName = is_debug() ? 'php_proxy_hunter_test' : ($_ENV['MYSQL_DBNAME'] ?? getenv('MYSQL_DBNAME'));
  $dbUser = $_ENV['MYSQL_USER'] ?? getenv('MYSQL_USER');
  $dbPass = $_ENV['MYSQL_PASS'] ?? getenv('MYSQL_PASS');
  $dbHost = $_ENV['MYSQL_HOST'] ?? getenv('MYSQL_HOST');
  $dbFile = is_debug() ? get_project_root('tmp/database_test.sqlite') : get_project_root('src/database.sqlite');
  // Github CI uses SQLite for testing to avoid needing a MySQL service
  $dbType = is_github_ci() ? 'sqlite' : 'mysql';
  return new CoreDB(
    $dbFile,
    $dbHost,
    $dbName,
    $dbUser,
    $dbPass,
    false,
    $dbType
  );
}
