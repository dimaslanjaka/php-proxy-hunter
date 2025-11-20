<?php

/**
 * Run background command (cross-platform).
 *
 * @param string $cmd       Base command ("php", "node", "python", etc)
 * @param array<string>  $args      Array of arguments (already escaped or raw)
 * @param string|null $outputFile Optional output redirection target
 * @example
 * ```php
 * runCmdInBackground('node', [
 *     escapeshellarg('path/to/script.js'),
 *     '--option=' . escapeshellarg('value'),
 * ], 'path/to/output.log');
 * ```
 */
function runCmdInBackground($cmd, $args = [], $outputFile = null) {
  $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

  // Build final command with proper escaping
  $parts   = [];
  $parts[] = escapeshellcmd($cmd);

  foreach ($args as $arg) {
    $parts[] = $arg;
  }

  $fullCmd = implode(' ', $parts);

  // Output redirect
  if ($outputFile) {
    $redirect = $isWin
          ? '> ' . escapeshellarg($outputFile) . ' 2>&1'
          : '> ' . escapeshellarg($outputFile) . ' 2>&1';
  } else {
    $redirect = $isWin ? '> NUL 2>&1' : '> /dev/null 2>&1';
  }

  // Background execution
  if ($isWin) {
    // Example: cmd /C start "" /B php script.php > log.txt 2>&1
    $background = 'cmd /C start "" /B ' . $fullCmd . ' ' . $redirect;
    @pclose(@popen($background, 'r'));
  } else {
    // Example: php script.php > log.txt 2>&1 &
    @exec($fullCmd . ' ' . $redirect . ' &');
  }
}
