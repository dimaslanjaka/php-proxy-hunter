<?php

require_once __DIR__ . '/../../../func.php';

$output_file = tmp() . '/logs/sample-output-method-2.txt';
truncateFile($output_file);

echo "Output file: $output_file\n";

$script = __DIR__ . '/sample-error.php';
$cmd    = 'php ' . escapeshellarg($script);
// Properly escape for Windows shell
$escapedCmd = escapeshellcmd($cmd);

// Redirect stdout and stderr to log file and run in background
exec("start /B cmd /C \"$escapedCmd >> \"$output_file\" 2>&1\"");

echo "\n=== LOG OUTPUT ===\n";
echo file_get_contents($output_file);
echo "\n=== END LOG OUTPUT ===\n";
