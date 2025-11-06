<?php

/**
 * Create a batch (.bat) or bash (.sh) runner script file for the given filename and command.
 *
 * This utility will:
 * - Detect the current OS and choose the proper extension (.bat for Windows, .sh for Unix).
 * - Sanitize the provided filename.
 * - Ensure the runners temporary directory is expressed in a Unix-style path.
 * - Write the provided command content into the runner script file.
 *
 * Notes:
 * - The function returns the full path to the created runner script.
 * - It does not attempt to execute the script; it only creates/writes the runner file.
 *
 * @param string $filename A desired filename (will be sanitized and given an OS-appropriate extension).
 * @param string $command  The command contents to write into the runner script file.
 *
 * @return string Full path to the created runner script.
 */
function createBatchOrBashRunner($filename, $command) {
  $isWin      = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  $runnerDir  = unixPath(tmp() . '/runners');
  $filename   = sanitizeFilename($filename) . ($isWin ? '.bat' : '.sh');
  $runnerPath = unixPath($runnerDir . '/' . $filename);
  write_file($runnerPath, $command);
  return $runnerPath;
}

/**
 * Quote an argument for Windows cmd.exe (double-quote and double internal quotes).
 *
 * @param string $str
 * @return string
 */
function quoteWindowsArg($str) {
  // convert forward slashes to backslashes for Windows paths
  $s = str_replace('/', '\\', $str);
  // double any internal double-quotes and wrap in double-quotes
  return '"' . str_replace('"', '""', $s) . '"';
}

/**
 * Executes a Bash or Batch script asynchronously with optional arguments.
 *
 * - Automatically builds a command line from provided arguments.
 * - Writes a shell or batch runner script into the temporary directory.
 * - Uses Python virtual environment activation if available.
 * - Executes the script in the background.
 *
 * By default this function does NOT redirect the script's stdout/stderr into a
 * log file (redirecting is opt-in). When `$redirectOutput` is set to true the
 * script's stdout/stderr will be redirected into a log file located under
 * tmp/logs/<identifier>.txt. Callers that rely on capturing output should set
 * `$redirectOutput` to true.
 *
 * @param string $scriptPath  The path to the Bash (.sh) or Batch (.bat) script.
 * @param array  $commandArgs An associative array of arguments to pass to the script as --key=value.
 * @param string|null $identifier  Optional unique identifier used to name the runner and log files.
 * @param bool $redirectOutput When true stdout/stderr of the spawned script will be redirected into the log file.
 * @param string|null $customOutputPath Optional absolute path to use for redirected output.
 *
 * @return array{
 *   output: string,     // Full path to the output log file.
 *   cwd: string,        // Current working directory.
 *   relative: string,   // Relative path to the output log file.
 *   runner: string,     // Full path to the runner script file.
 *   command: string,    // Full command line used to invoke the script.
 * }|array{
 *   error: string       // Error message if script writing fails.
 * }
 */
function runBashOrBatch($scriptPath, $commandArgs = [], $identifier = null, $redirectOutput = false, $customOutputPath = null) {
  $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

  // Convert arguments to command line string
  $commandArgsString = '';
  foreach ($commandArgs as $key => $value) {
    if ($isWin) {
      $escapedValue = quoteWindowsArg((string)$value);
    } else {
      $escapedValue = escapeshellarg((string)$value);
    }
    $commandArgsString .= '--' . $key . '=' . $escapedValue . ' ';
  }
  $commandArgsString = trim($commandArgsString);

  $cwd = get_project_root();

  // Read script content safely (avoid null coalescing for PHP 7.0)
  $fileData      = file_get_contents($scriptPath);
  $scriptContent = isset($fileData) ? trim($fileData) : '';
  $hash          = md5($scriptPath . '/' . $commandArgsString . '/' . $scriptContent);

  // Determine filename for runner and log file
  if ($redirectOutput && !empty($customOutputPath)) {
    $name     = pathinfo($scriptPath, PATHINFO_FILENAME);
    $filename = sanitizeFilename($name . '-' . $hash);
  } else {
    if (!empty($identifier)) {
      $filename = sanitizeFilename($identifier);
    } else {
      $hash     = md5($scriptPath . '/' . $commandArgsString);
      $name     = pathinfo($scriptPath, PATHINFO_FILENAME);
      $filename = sanitizeFilename($name . '-' . $hash);
    }
  }

  // Build runner and log paths
  $runner_file = unixPath(tmp() . '/runners/' . $filename . '-runBashOrBatch-' . $hash . ($isWin ? '.bat' : '.sh'));

  if ($redirectOutput && !empty($customOutputPath)) {
    $output_file = unixPath($customOutputPath);
  } else {
    $output_file = unixPath(tmp() . '/logs/' . $filename . '-runBashOrBatch-' . $hash . '.txt');
  }

  // Construct command
  $venvPath = !$isWin ? $cwd . '/venv/bin/activate' : $cwd . '/venv/Scripts/activate';
  $venv     = realpath($venvPath);

  $cmdParts = [];
  if ($venv) {
    $cmdParts[] = $isWin ? 'call ' . quoteWindowsArg($venv) : 'source ' . escapeshellarg($venv);
  }

  $escapedOutput = $isWin ? quoteWindowsArg($output_file) : escapeshellarg($output_file);

  if ($isWin) {
    $invoke = 'call ' . quoteWindowsArg($scriptPath);
  } else {
    $invoke = 'bash ' . escapeshellarg($scriptPath);
  }

  if ($redirectOutput) {
    $cmdParts[] = $invoke . ' > ' . $escapedOutput . ' 2>&1';
  } else {
    $cmdParts[] = $invoke;
  }

  $cmd = trim(implode(' && ', $cmdParts));

  // Truncate files
  truncateFile($output_file);
  truncateFile($runner_file);

  // Write command to runner file (Windows uses CRLF)
  if ($isWin) {
    $cmdToWrite = preg_replace("/\r?\n/", "\r\n", $cmd);
  } else {
    $cmdToWrite = $cmd;
  }
  write_file($runner_file, $cmdToWrite);

  // Change current working directory
  chdir($cwd);

  // Execute in background
  if ($isWin) {
    $startCmd = 'start "" /B ' . quoteWindowsArg(unixPath($runner_file));
    pclose(popen($startCmd, 'r'));
  } else {
    exec('bash ' . escapeshellarg($runner_file) . ' > /dev/null 2>&1 &');
  }

  return [
    'error'   => false,
    'message' => json_encode([
      'output'   => unixPath($output_file),
      'cwd'      => unixPath($cwd),
      'relative' => str_replace(unixPath($cwd), '', unixPath($output_file)),
      'runner'   => $runner_file,
      'command'  => $cmd,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
  ];
}
