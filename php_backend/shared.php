<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\ProxyDB;
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
$proxy_db = new ProxyDB(__DIR__ . '/../src/database.sqlite');
$dbName   = is_debug() ? 'php_proxy_hunter_test' : ($_ENV['MYSQL_DBNAME'] ?? getenv('MYSQL_DBNAME'));
$core_db  = new CoreDB(
  __DIR__ . '/../src/database.sqlite',
  $_ENV['MYSQL_HOST'] ?? getenv('MYSQL_HOST'),
  $dbName,
  $_ENV['MYSQL_USER'] ?? getenv('MYSQL_USER'),
  $_ENV['MYSQL_PASS'] ?? getenv('MYSQL_PASS'),
  false,
  'mysql'
);
/** @var \PhpProxyHunter\UserDB $user_db */
$user_db = $core_db->user_db;
