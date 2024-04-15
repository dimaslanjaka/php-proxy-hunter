<?php

// config json files cleaner

// Directory where JSON files are located
$directory = __DIR__ . '/config/';

// Get the current timestamp
$current_time = time();

// Calculate the timestamp for 1 week ago
$one_week_ago = $current_time - (7 * 24 * 60 * 60); // 7 days * 24 hours * 60 minutes * 60 seconds

// Open the directory
if ($handle = opendir($directory)) {
  // Loop through each file in the directory
  while (false !== ($file = readdir($handle))) {
    // Check if the file is a JSON file
    if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
      // Get the last modification time of the file
      $file_mtime = filemtime($directory . $file);

      // Check if the file was created exactly 1 week ago
      if ($file_mtime === $one_week_ago) {
        // Remove the file
        unlink($directory . $file);
        echo "File $file removed.\n";
      }
    }
  }
  // Close the directory handle
  closedir($handle);
}
