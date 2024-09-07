<?php

// config json files cleaner

require_once __DIR__ . '/func.php';

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  header('Content-Type:text/plain; charset=UTF-8');
}
if (!$isCli) {
  exit('web server access disallowed');
}

// Directory where JSON files are located
$directories = [
  __DIR__ . '/config/',
  __DIR__ . '/.cache/',
  __DIR__ . '/tmp/',
  __DIR__ . '/tmp/cookies/',
  __DIR__ . '/tmp/sessions/',
  __DIR__ . '/tmp/runners/',
  __DIR__ . '/tmp/logs/',
  __DIR__ . '/backups/'
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
      $pattern = '/\.(db|sqlite|sqlite3|mmdb)$/i';
      if (preg_match($pattern, $filePath)) {
        echo "$filePath excluded";
        continue;
      }

      // Get the last modification time of the file
      $file_mtime = filemtime($filePath);

      // File was last modified more than 1 week ago.
      if ($file_mtime < $oneWeekAgo) {
        // Remove the file
        echo "File $file removed (" . (unlink($filePath) ? 'success' : 'failed') . ")" . PHP_EOL;
      }
    }
    // Close the directory handle
    closedir($handle);
  }
}
