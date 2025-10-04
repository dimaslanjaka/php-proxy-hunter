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

$fileContent = read_file($firstFile);
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

// simple: remove exact-match lines and write back in-place
// read lines directly from the file to avoid relying on $fileContent for removal
$lines   = file($firstFile, FILE_IGNORE_NEW_LINES);
$out     = [];
$removed = 0;
foreach ($lines as $ln) {
  $trim         = trim($ln);
  $shouldRemove = false;
  if ($trim !== '') {
    // exact match
    if (in_array($trim, $str_to_remove, true)) {
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
  $out[] = $ln;
}

if ($removed > 0) {
  file_put_contents($firstFile, implode(PHP_EOL, $out) . PHP_EOL);
  echo "Removed $removed lines from " . $firstFile . PHP_EOL;
} else {
  echo 'No lines removed from ' . $firstFile . PHP_EOL;
}
