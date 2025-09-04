<?php

// PHPUnit bootstrap file to load .env early
require_once __DIR__ . '/../vendor/autoload.php';

// Always load .env from project root
if (class_exists('Dotenv\\Dotenv')) {
  $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1));
  $dotenv->load();
}
