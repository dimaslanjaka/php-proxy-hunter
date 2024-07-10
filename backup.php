<?php

// access https://sh.webmanajemen.com/data/cookies.html
// put your admin cookie into tmp/cookies/default.txt
// curl -b tmp/cookies/default.txt -o tmp/database.sqlite https://sh.webmanajemen.com/backup.php
// change sh.webmanajemen.com with your own domain

require_once __DIR__ . '/func.php';

global $isCli, $isAdmin;

if ($isCli) {
  exit('CLI access disallowed');
}

if (!$isAdmin) {
  exit('Access denied');
}

$file = __DIR__ . '/src/database.sqlite';

// Check if the file exists
if (file_exists($file)) {
  // Set headers for file download
  header('Content-Description: File Transfer');
  header('Content-Type: application/octet-stream');
  header('Content-Disposition: attachment; filename="' . basename($file) . '"');
  header('Expires: 0');
  header('Cache-Control: must-revalidate');
  header('Pragma: public');
  header('Content-Length: ' . filesize($file));
  // Clear output buffer
  ob_clean();
  // Flush output buffer
  flush();
  // Read the file and output it to the browser
  readfile($file);
  exit;
} else {
  // File not found error handling
  echo "File not found.";
}
