<?php

// config json files cleaner

require_once __DIR__ . '/../func.php';

$isCli = (php_sapi_name() === 'cli' || defined('STDIN'));

if (!$isCli) {
  header('Content-Type:text/plain; charset=UTF-8');
  exit('web server access disallowed');
}

// Directory where JSON files are located
$directories = [
  __DIR__ . '/../config/',
  __DIR__ . '/../.cache/',
  __DIR__ . '/../tmp/',
  __DIR__ . '/../tmp/cookies/',
  __DIR__ . '/../tmp/sessions/',
  __DIR__ . '/../tmp/runners/',
  __DIR__ . '/../tmp/locks/',
  __DIR__ . '/../tmp/logs/',
  __DIR__ . '/../backups/',
];

// Get the current timestamp
$current_time = time();

// Calculate the timestamp for 1 week ago
$oneWeekAgo = strtotime('-1 week');

foreach ($directories as $directory) {
  if (!file_exists($directory)) {
    continue;
  }
  // Open the directory
  if ($handle = opendir($directory)) {
    // Loop through each file in the directory
    while (false !== ($file = readdir($handle))) {
      $filePath = realpath($directory . '/' . $file);
      if (!is_file($filePath)) {
        continue;
      }
      // skip database deletion
      $pattern = '/\.(db|sqlite|sqlite3|mmdb|.*-wal|.*-shm)$/i';
      if (preg_match($pattern, $filePath)) {
        echo "$filePath excluded" . PHP_EOL;
        continue;
      }

      // Get the last modification time of the file, fallback to creation time if needed
      $fileDate = @filemtime($filePath);
      if ($fileDate === false) {
        // Fallback: filectime is creation time on Windows, inode change time on Unix
        $fileDate = @filectime($filePath);
      }
      // Get using exec if both failed
      if ($fileDate === false) {
        try {
          if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = [];
            exec("for %I in (\"$filePath\") do @echo %~tI", $output);
            if (isset($output[0])) {
              $fileDate = strtotime($output[0]);
            }
          } else {
            $output = [];
            exec('stat -c %Y ' . escapeshellarg($filePath), $output);
            if (isset($output[0]) && is_numeric($output[0])) {
              $fileDate = (int)$output[0];
            }
          }
        } catch (Throwable $e) {
          // Log or handle the exception as needed
          $fileDate = false;
        }
      }

      // File was last modified (or created) more than 1 week ago.
      if ($fileDate !== false && $fileDate < $oneWeekAgo) {
        // Remove the file
        echo "File $file removed (" . (unlink($filePath) ? 'success' : 'failed') . ')' . PHP_EOL;
      }
    }
    // Close the directory handle
    closedir($handle);
  }
}
