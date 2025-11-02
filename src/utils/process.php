<?php

declare(strict_types=1);

/**
 * Execute a command in background (detached) on Windows and Unix.
 *
 * The command's stdout and stderr can be redirected to a file by
 * providing the optional $outputFile parameter. On Windows the file
 * path will be quoted; on Unix the command will redirect to the
 * provided file or to /dev/null when no file is supplied.
 *
 * @param string $cmd The full command to execute (e.g. PHP invocation).
 * @param string|null $outputFile Optional path to a file where stdout and stderr will be appended. If null, output is discarded.
 * @return void
 */
function execInBackground(string $cmd, ?string $outputFile = null): void
{
  $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

  // prepare redirection
  if (!empty($outputFile)) {
    // Windows cmd/powershell will accept a file path; ensure proper quoting
    $redir = ' > ' . escapeshellarg($outputFile) . ' 2>&1';
  } else {
    $redir = $isWin ? '' : ' > /dev/null 2>&1';
  }

  if ($isWin) {
    // Use start to detach the process on Windows. Popen+pclose returns immediately.
    $background = 'cmd /C start "" /B ' . $cmd . $redir;
    @pclose(@popen($background, 'r'));
  } else {
    // On Unix create a subshell and background it
    $background = $cmd . $redir . ' &';
    @exec($background);
  }
}
