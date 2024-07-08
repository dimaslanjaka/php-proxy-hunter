<?php

require_once __DIR__ . '/func.php';

global $isCli;

if ($isCli) {
  exit('CLI access disallowed');
}

$isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;

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
