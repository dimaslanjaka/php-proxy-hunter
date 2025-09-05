<?php

require_once __DIR__ . '/../func.php';

$sourceFile      = __DIR__ . '/../tmp/source.txt';
$destinationFile = __DIR__ . '/../tmp/destination.txt';
$linesToMove     = 50;

$lines = [];

// Generate 100 random strings
for ($i = 0; $i < 100; $i++) {
  $lines[] = generateRandomString();
}

// if (!file_exists(dirname($sourceFile))) mkdir(dirname($sourceFile));
// file_put_contents($sourceFile, implode(PHP_EOL, $lines));

if (moveLinesToFile($sourceFile, $destinationFile, $linesToMove)) {
  echo "First $linesToMove lines moved successfully from '$sourceFile' to '$destinationFile'.";
} else {
  echo "Failed to move lines from '$sourceFile' to '$destinationFile'.";
}
