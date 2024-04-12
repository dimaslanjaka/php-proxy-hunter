<?php

require_once __DIR__ . "/func.php";

// clean all proxies
// merged into proxies-all.txt

$all = __DIR__ . '/proxies-all.txt';
$untested = __DIR__ . '/proxies.txt';
$working = __DIR__ . '/working.txt';
$dead = __DIR__ . '/dead.txt';

setFilePermissions([$all, $untested, $working, $dead]);

// Open all files in read mode
$untested_content = file_get_contents($untested);
$working_content = file_get_contents($working);
$dead_content = file_get_contents($dead);
$all_content = file_get_contents($all);

// Merge contents
$merged_content = $all_content . PHP_EOL . $untested_content . PHP_EOL . $working_content . PHP_EOL . $dead_content;

// Write merged content to $all
file_put_contents($all, $merged_content);

// Optional: Clear contents of $untested, $working, and $dead files
file_put_contents($untested, '');
file_put_contents($working, '');
file_put_contents($dead, '');

// unique proxies
rewriteIpPortFile($all);

echo "Contents merged and moved to $all successfully.";
