<?php


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
 * @param array $commandArgs  An associative array of arguments to pass to the script as --key=value.
 * @param string|null $identifier  Optional unique identifier used to name the runner and log files.
 * @param bool $redirectOutput (optional) When true stdout/stderr of the spawned
 *   script will be redirected into the log file. When false (default) the
 *   script will be invoked without redirecting output.
 *
 * @return array{
 *   output: string,     // Full path to the output log file.
 *   cwd: string,        // Current working directory.
 *   relative: string,   // Relative path to the output log file.
 *   runner: string      // Full path to the runner script file.
 * }|array{
 *   error: string       // Error message if script writing fails.
 * }
 */
function runBashOrBatch($scriptPath, $commandArgs = [], $identifier = null, $redirectOutput = false)
{
  global $isWin;

  // Convert arguments to command line string
  $commandArgsString = '';
  foreach ($commandArgs as $key => $value) {
    $escapedValue = escapeshellarg($value);
    $commandArgsString .= "--$key=$escapedValue ";
  }
  $commandArgsString = trim($commandArgsString);

  // Determine paths and commands
  $cwd = __DIR__;

  if (!empty($identifier)) {
    $filename = sanitizeFilename($identifier);
  } else {
    $hash     = md5("$scriptPath/$commandArgsString");
    $name     = pathinfo($scriptPath, PATHINFO_FILENAME);
    $filename = sanitizeFilename($name . '-' . $hash);
  }

  $runner      = unixPath(tmp() . "/runners/$filename" . ($isWin ? '.bat' : '.sh'));
  $output_file = unixPath(tmp() . "/logs/$filename.txt");
  $pid_file    = unixPath(tmp() . "/runners/$filename.pid");

  // Truncate output file
  truncateFile($output_file);

  // Construct the command
  $venv     = !$isWin ? realpath("$cwd/venv/bin/activate") : realpath("$cwd/venv/Scripts/activate");
  $venvCall = $isWin ? "call $venv" : "source $venv";

  $cmd = $venvCall;
  // Optionally ensure output is redirected to the output file and no output is echoed
  if ($redirectOutput) {
    if ($isWin) {
      // On Windows, call the script and redirect stdout/stderr to the log file
      $cmd .= " && call $scriptPath > " . escapeshellarg($output_file) . ' 2>&1';
    } else {
      // On Unix, run the script with bash and redirect stdout/stderr to the log file
      $cmd .= " && bash $scriptPath > " . escapeshellarg($output_file) . ' 2>&1';
    }
  } else {
    // Don't redirect output; just call the script normally
    if ($isWin) {
      $cmd .= " && call $scriptPath";
    } else {
      $cmd .= " && bash $scriptPath";
    }
  }
  $cmd = trim($cmd);

  // Write command to runner script
  $write = write_file($runner, $cmd);
  if (!$write) {
    return ['error' => 'Failed writing shell script ' . $runner];
  }

  // Change current working directory
  chdir($cwd);

  // Execute the runner script
  if ($isWin) {
    // Use start with redirect and /B to run without creating a new window
    // Redirect is already handled inside runner script, ensure command is quoted
    $runner_win = 'start /B "window_name" ' . escapeshellarg(unixPath($runner));
    pclose(popen($runner_win, 'r'));
  } else {
    // Execute the runner script in background; runner already redirects output
    exec('bash ' . escapeshellarg($runner) . ' > /dev/null 2>&1 &');
  }

  return [
    'output'   => unixPath($output_file),
    'cwd'      => unixPath($cwd),
    'relative' => str_replace(unixPath($cwd), '', unixPath($output_file)),
    'runner'   => $runner,
  ];
}
