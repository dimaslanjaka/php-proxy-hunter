<?php

// Sample PHP script used by tests. It writes to stdout and stderr,
// writes a small status file with the intended exit code, then exits
// with a non-zero status to simulate an error condition.

echo "OUT:hello\n";
fwrite(STDERR, "ERR:boom\n");

// Define the path to the status file for easier reuse and clarity.
$statusFile = __DIR__ . '/../../../logs/sample-error-status.txt';
if (!is_dir(dirname($statusFile))) {
  mkdir(dirname($statusFile), 0777, true);
}
$statusFile = realpath($statusFile);

// Write the exit code to a status file so the asynchronous test can verify
// the script actually exited with the intended code.
$exitCode = 5;
file_put_contents($statusFile, (string) $exitCode);

exit($exitCode);
