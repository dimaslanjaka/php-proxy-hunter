<?php

require_once __DIR__ . '/../bootstrap.php';

use PhpProxyHunter\CoreDB;
use PhpProxyHunter\LogsRepositoryMigrations;

$providers = ['mysql', 'sqlite'];

foreach ($providers as $driver) {
  echo "Testing with driver: $driver\n";
  if ($driver === 'mysql') {
    $db = new CoreDB(null, $_ENV['MYSQL_HOST'] ?? getenv('DB_HOST'), 'php_proxy_hunter_test', $_ENV['MYSQL_USER'] ?? getenv('DB_USER'), $_ENV['MYSQL_PASS'] ?? getenv('DB_PASS'), true, 'mysql');
  } else {
    $db = new CoreDB(__DIR__ . '/tmp/test_database.sqlite');
  }
  $migration = new LogsRepositoryMigrations($db->db->pdo, $driver);
  $migration->migrateIfNeeded();
  echo "Migration completed for driver: $driver\n";
}
