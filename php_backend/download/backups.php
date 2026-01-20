<?php

/**
 * Download Backups Script
 *
 * This script handles the downloading of backup files from the server.
 * Only administrators are allowed to access this functionality.
 */

require_once __DIR__ . '/../shared.php';

\PhpProxyHunter\Server::allowCors(false);

$isAdmin = is_admin() || is_debug_device();
if (!$isAdmin) {
  http_response_code(403);
  respond_json(['error' => true, 'message' => 'Access denied. Administrator privileges required.']);
}

$backupDir = __DIR__ . '/../../backups/';

// Normalize and ensure trailing slash
$backupDir = rtrim($backupDir, '\\/') . DIRECTORY_SEPARATOR;

if (!is_dir($backupDir)) {
  respond_json(['error' => true, 'message' => 'Backups directory not found.'], 500);
}

try {
  $realBase = realpath($backupDir);
  if ($realBase === false) {
    respond_json(['error' => true, 'message' => 'Unable to resolve backups directory.'], 500);
  }

  // If a specific path is requested, serve the file for download (admin only)
  if (!empty($_GET['path'])) {
    $requested = rawurldecode((string)$_GET['path']);
    // Normalize separators and remove any leading slashes
    $requested = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $requested), DIRECTORY_SEPARATOR);
    $target    = realpath($realBase . DIRECTORY_SEPARATOR . $requested);
    if ($target === false || !is_file($target)) {
      respond_json(['error' => true, 'message' => 'Requested file not found.'], 404);
    }
    // Ensure the target is inside backups directory
    if (strpos($target, $realBase) !== 0) {
      respond_json(['error' => true, 'message' => 'Access denied.'], 403);
    }

    $filename = basename($target);
    $size     = filesize($target);
    $mime     = (function_exists('mime_content_type') ? mime_content_type($target) : 'application/octet-stream');

    // Send download headers and stream the file
    if (!headers_sent()) {
      header('Content-Description: File Transfer');
      header('Content-Type: ' . $mime);
      header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
      header('Content-Transfer-Encoding: binary');
      header('Expires: 0');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');
      header('Content-Length: ' . $size);
    }
    // Flush output buffers
    while (ob_get_level()) {
      ob_end_clean();
    }
    readfile($target);
    exit(0);
  }

  $it    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realBase, RecursiveDirectoryIterator::SKIP_DOTS));
  $files = [];
  // Build base URL for file links (current script path without query)
  $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host     = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
  $pathOnly = parse_url($_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? ''), PHP_URL_PATH);
  $baseUrl  = $scheme . '://' . $host . $pathOnly;
  foreach ($it as $file) {
    if (!$file->isFile()) {
      continue;
    }
    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if ($ext !== 'sql' && $ext !== 'json') {
      continue;
    }

    $filePath = $file->getRealPath();
    if ($filePath === false) {
      continue;
    }

    // Build path relative to backups directory, using forward slashes
    $relative = str_replace('\\', '/', ltrim(substr($filePath, strlen($realBase)), DIRECTORY_SEPARATOR));
    if ($relative === '') {
      $relative = $file->getFilename();
    }

    $mtime   = $file->getMTime();
    $files[] = [
      'name'        => $file->getFilename(),
      'path'        => $relative,
      'size'        => human_filesize($file->getSize()),
      'size_bytes'  => $file->getSize(),
      'abs_path'    => $filePath,
      'modified'    => gmdate(DATE_ATOM, $mtime),
      'modified_ts' => $mtime,
      'type'        => $ext,
      'url'         => $baseUrl . '?path=' . rawurlencode($relative),
    ];
  }

  // Sort by modified time desc
  usort($files, function ($a, $b) {
    return $b['modified_ts'] <=> $a['modified_ts'];
  });

  respond_json(['error' => false, 'files' => $files]);
} catch (Throwable $e) {
  respond_json(['error' => true, 'message' => 'Failed scanning backups: ' . $e->getMessage()], 500);
}
