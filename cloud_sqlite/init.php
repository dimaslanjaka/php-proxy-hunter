<?php
declare(strict_types=1);

require_once __DIR__ . '/../func.php';
require_once __DIR__ . '/config.php';

/**
 * Initialize and return a PDO connection to the SQLite database.
 *
 * @return PDO
 */
function getPdoConnection(): PDO
{
  $pdo = new PDO('sqlite:' . DB_FILE);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return $pdo;
}

$pdo = getPdoConnection();

// Always ensure the table exists
$pdo->exec(
  <<<SQL
CREATE TABLE IF NOT EXISTS items (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  value TEXT,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
);
