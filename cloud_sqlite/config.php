<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

if (!isset($_ENV['CLOUD_SQLITE_SECRET'])) {
  throw new RuntimeException('CLOUD_SQLITE_SECRET environment variable is not set.');
}
define('AUTH_TOKEN', $_ENV['CLOUD_SQLITE_SECRET']);
define('DB_FILE', __DIR__ . '/db.sqlite');
