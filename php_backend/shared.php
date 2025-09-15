<?php

require_once __DIR__ . '/../func-proxy.php';

use PhpProxyHunter\CoreDB;

// Disallow access to this file directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
  http_response_code(403);
  exit('Direct access not permitted.');
}

// Always load .env from project root
if (class_exists('Dotenv\\Dotenv')) {
  $dotenv = Dotenv\Dotenv::createUnsafeImmutable(dirname(__DIR__, 1));
  $dotenv->load();
  // For compatibility with libraries using getenv()
  foreach ($_ENV as $key => $value) {
    putenv("$key=$value");
  }
}

// Declare a database connection variable
$dbName  = is_debug() ? 'php_proxy_hunter_test' : ($_ENV['MYSQL_DBNAME'] ?? getenv('MYSQL_DBNAME'));
$dbUser  = is_debug_device() ? ($_ENV['MYSQL_USER'] ?? getenv('MYSQL_USER')) : ($_ENV['MYSQL_USER_PRODUCTION'] ?? getenv('MYSQL_USER_PRODUCTION'));
$dbPass  = is_debug_device() ? ($_ENV['MYSQL_PASS'] ?? getenv('MYSQL_PASS')) : ($_ENV['MYSQL_PASS_PRODUCTION'] ?? getenv('MYSQL_PASS_PRODUCTION'));
$dbHost  = is_debug_device() ? ($_ENV['MYSQL_HOST'] ?? getenv('MYSQL_HOST')) : ($_ENV['MYSQL_HOST_PRODUCTION'] ?? getenv('MYSQL_HOST_PRODUCTION'));
$dbFile  = is_debug() ? __DIR__ . '/../tmp/database_test.sqlite' : __DIR__ . '/../src/database.sqlite';
$core_db = new CoreDB(
  $dbFile,
  $dbHost,
  $dbName,
  $dbUser,
  $dbPass,
  false,
  'mysql'
);
/** @var \PhpProxyHunter\UserDB $user_db */
$user_db = $core_db->user_db;
/** @var \PhpProxyHunter\ProxyDB $proxy_db */
$proxy_db = $core_db->proxy_db;
