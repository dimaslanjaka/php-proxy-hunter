<?php

include __DIR__ . '/config.php';

$db = new SQLite3(DB_FILE);

if (!file_exists(DB_FILE)) {
  $db->exec("
CREATE TABLE IF NOT EXISTS items (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  value TEXT,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
  ");
}
