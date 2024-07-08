<?php

require_once __DIR__ . '/func-proxy.php';

global $isCli;

if (!$isCli) {
  exit('web server disallowed');
}

ini_set('memory_limit', '1024M'); // Increase memory limit if needed

// put *.sqlite in tmp/ folder

$sourceDir = __DIR__ . '/tmp/';
$targetDbPath = __DIR__ . '/src/database.sqlite'; // $sourceDir . 'merged.sqlite';
$chunkSize = 1000; // Number of rows to process at a time

try {
  // Create or open the target SQLite database
  $targetDb = new PDO('sqlite:' . $targetDbPath);
  $targetDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Get all .sqlite files from the source directory, excluding the merged.sqlite
  $sourceFiles = glob($sourceDir . '*.sqlite');
  $sourceFiles = array_filter($sourceFiles, function ($file) use ($targetDbPath) {
    return realpath($file) !== realpath($targetDbPath);
  });

  foreach ($sourceFiles as $sourceFile) {
    // Open the current source SQLite database
    $sourceDb = new PDO('sqlite:' . $sourceFile);
    $sourceDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch all tables from the source database
    $tablesResult = $sourceDb->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $tablesResult->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
      // Get column names
      $columnsResult = $sourceDb->query("PRAGMA table_info($table)");
      $columns = $columnsResult->fetchAll(PDO::FETCH_COLUMN, 1);

      // Check if table exists in the target database
      $tableExists = $targetDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'")->fetchColumn();

      // Create table if not exists in the target database
      if (!$tableExists) {
        $createTableSql = $sourceDb->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'")->fetchColumn();
        if ($createTableSql === false) {
          throw new Exception("Failed to fetch create table SQL for table $table in file $sourceFile");
        }
        $targetDb->exec($createTableSql);
      }

      // Prepare insert statement for the target database
      $columnsList = implode(',', $columns);
      $placeholders = implode(',', array_fill(0, count($columns), '?'));
      $insertStmt = $targetDb->prepare("INSERT OR IGNORE INTO $table ($columnsList) VALUES ($placeholders)");

      if ($insertStmt === false) {
        throw new Exception("Failed to prepare insert statement for table $table in file $sourceFile");
      }

      // Fetch and insert data in chunks
      $offset = 0;
      do {
        $dataResult = $sourceDb->query("SELECT * FROM $table LIMIT $chunkSize OFFSET $offset");
        if ($dataResult === false) {
          throw new Exception("Failed to fetch data from table $table in file $sourceFile");
        }
        $data = $dataResult->fetchAll(PDO::FETCH_ASSOC);
        $offset += $chunkSize;

        foreach ($data as $row) {
          if ($insertStmt->execute(array_values($row)) === false) {
            throw new Exception("Failed to execute insert statement for table $table in file $sourceFile");
          }
        }
      } while (count($data) === $chunkSize);
    }
  }

  echo "Databases merged successfully!";
} catch (Exception $e) {
  echo "An error occurred: " . $e->getMessage();
}
