<?php

require_once __DIR__ . "/func.php";

// clean all proxies
// merged into proxies-all.txt

$all = __DIR__ . '/proxies-all.txt';

// Define file paths array
$files = [
  __DIR__ . '/proxies.txt',
  __DIR__ . '/working.txt',
  __DIR__ . '/dead.txt',
  __DIR__ . '/socks.txt',
  __DIR__ . '/socks-working.txt',
  __DIR__ . '/socks-dead.txt',
];

setFilePermissions($all);

// Open all files in read mode
$all_content = file_get_contents($all);

// Merge contents
$merged_content = $all_content . PHP_EOL;

// Iterate through the files array and truncate each file
foreach ($files as $file) {
  $content = file_get_contents($file);
  $merged_content = $merged_content . PHP_EOL . $content . PHP_EOL;
  truncateFile($file);
}

// Write merged content to $all
file_put_contents($all, $merged_content);

// unique proxies
removeDuplicateLines($all);

echo "Contents merged and moved to $all successfully.";

// Function to truncate the content of a file
function truncateFile($filePath)
{
  file_put_contents($filePath, ''); // Write an empty string to truncate the file
}
