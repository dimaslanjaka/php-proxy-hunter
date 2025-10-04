<?php

require_once __DIR__ . '/../func-proxy.php';
require_once __DIR__ . '/../php_backend/shared.php';

global $isCli, $proxy_db;

$assetDir = __DIR__ . '/../assets';
$proxyDir = $assetDir . '/proxies';
if (!is_dir($proxyDir)) {
  mkdir($proxyDir, 0777, true);
}

// get all files in directory
$files = glob($proxyDir . '/*.txt');
if ($files === false) {
  exit('failed to read proxy directory');
}
if (count($files) === 0) {
  exit('no proxy file found');
}

$files = array_map('realpath', $files);
// filter only file starting with added-
$files = array_filter($files, function ($file) {
  return str_starts_with(basename($file), 'added-');
});
sort($files);

$firstFile = reset($files);
if ($firstFile === false) {
  exit('no proxy file found');
}

$fileContent = readFileChunk($firstFile, 20 * 1024); // read in KB
if (is_string($fileContent) === false || trim($fileContent) === '') {
  // delete the empty file and exit
  delete_path($firstFile);
  exit('file is empty: ' . $firstFile);
}
$extractProxies = extractProxies($fileContent, $proxy_db, true, 1000, true);

// delete proxy string from file
$str_to_remove       = [];
$skipped_count       = 0;
$already_added_count = 0;
$invalid_count       = 0;
$kept_count          = 0;
foreach ($extractProxies as $item) {
  $p = $item->proxy ?? '';
  if (empty($p)) {
    $skipped_count++;
    continue;
  }

  // invalid proxies are always removed
  if (!isValidProxy($p)) {
    $proxy_db->remove($p);
    $str_to_remove[] = $p;
    $invalid_count++;
    echo 'Invalid proxy (will remove): ' . $p . PHP_EOL;
    continue;
  }

  // add proxy when not already added
  if (!$proxy_db->isAlreadyAdded($p) && isValidProxy($p)) {
    $proxy_db->add($p);
  }

  if ($proxy_db->isAlreadyAdded($p)) {
    $str_to_remove[] = $p;
    $already_added_count++;
    echo 'Already added (will remove): ' . $p . PHP_EOL;
  } else {
    echo 'Kept (not added): ' . $p . PHP_EOL;
    $kept_count++;
  }
}

$total_remove = $already_added_count + $invalid_count;
echo sprintf('Found %d already-added and %d invalid proxies (total %d will be removed) in %s', $already_added_count, $invalid_count, $total_remove, $firstFile) . PHP_EOL;

// stream: process line-by-line and write kept lines to a temp file, then
// atomically replace the original file. This avoids loading large files
// entirely into memory.
$removed  = 0;
$tempFile = $firstFile . '.tmp';
$in       = fopen($firstFile, 'r');
if ($in === false) {
  exit('failed to open file for reading: ' . $firstFile);
}
$out = fopen($tempFile, 'w');
if ($out === false) {
  fclose($in);
  exit('failed to open temp file for writing: ' . $tempFile);
}

// optional: build a lookup set for exact-match checks for O(1) lookups
$lookup = [];
foreach ($str_to_remove as $s) {
  if ($s !== '') {
    $lookup[$s] = true;
  }
}

while (($ln = fgets($in)) !== false) {
  $trim         = trim($ln);
  $shouldRemove = false;
  if ($trim !== '') {
    // exact match
    if (isset($lookup[$trim])) {
      $shouldRemove = true;
    } else {
      // or contains any of the proxies as substring (handles noise)
      foreach ($str_to_remove as $probe) {
        if ($probe !== '' && strpos($trim, $probe) !== false) {
          $shouldRemove = true;
          break;
        }
      }
    }
  }
  if ($shouldRemove) {
    $removed++;
    continue;
  }
  // write the original line (preserve newline)
  fwrite($out, rtrim($ln, "\r\n") . PHP_EOL);
}

fclose($in);
fflush($out);
fclose($out);

if ($removed > 0) {
  // atomic replace on same filesystem
  if (!rename($tempFile, $firstFile)) {
    // fallback: try copy+unlink
    if (!copy($tempFile, $firstFile)) {
      exit('failed to replace original file with temp file');
    }
    unlink($tempFile);
  }
  echo "Removed $removed lines from " . $firstFile . PHP_EOL;
} else {
  // no changes: remove temp file
  @unlink($tempFile);
  echo 'No lines removed from ' . $firstFile . PHP_EOL;
}
