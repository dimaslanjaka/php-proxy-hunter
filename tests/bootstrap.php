<?php
// PHPUnit bootstrap file to load .env early
require_once __DIR__ . '/../vendor/autoload.php';
if (file_exists(__DIR__ . '/../../.env')) {
  (Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1)))->load();
}
