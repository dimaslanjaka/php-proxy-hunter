<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\UserDB;
use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\CoreDB;

// Disallow access to this file directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
  http_response_code(403);
  exit('Direct access not permitted.');
}

// Declare a database connection variable
$user_db  = new UserDB(null, 'mysql', $_ENV['MYSQL_HOST'], $_ENV['MYSQL_DBNAME'], $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASS']);
$proxy_db = new ProxyDB(__DIR__ . '/../src/database.sqlite');
$core_db  = new CoreDB(__DIR__ . '/../src/database.sqlite', 'mysql', $_ENV['MYSQL_HOST'], $_ENV['MYSQL_DBNAME'], $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASS']);
