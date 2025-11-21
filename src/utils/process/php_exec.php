<?php


/**
 * Executes a shell command using the available PHP functions.
 *
 * @param string $cmd The shell command to execute.
 * @return string The command output or an error message.
 */
function php_exec($cmd) {
  // Check for `exec` support
  if (function_exists('exec')) {
    $output     = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);
    return implode(' ', $output);
  } elseif (function_exists('shell_exec')) {
    // Check for `shell_exec` support
    return shell_exec($cmd);
  } elseif (function_exists('system')) {
    // Check for `system` support
    $return_var = 0;
    ob_start();
    system($cmd, $return_var);
    $output = ob_get_clean();
    return $output;
  } elseif (function_exists('passthru')) {
    // Check for `passthru` support
    $return_var = 0;
    ob_start();
    passthru($cmd, $return_var);
    $output = ob_get_clean();
    return $output;
  } elseif (function_exists('proc_open')) {
    // Check for `proc_open` support
    $descriptorspec = [
      0 => ['pipe', 'r'], // STDIN
      1 => ['pipe', 'w'], // STDOUT
      2 => ['pipe', 'w'], // STDERR
    ];

    $proc = proc_open($cmd, $descriptorspec, $pipes);
    if (is_resource($proc)) {
      $output = stream_get_contents($pipes[1]);
      fclose($pipes[1]);
      fclose($pipes[2]);
      proc_close($proc);
      return $output;
    } else {
      return 'Error: Unable to execute command using proc_open.';
    }
  } else {
    // No suitable function available
    return 'Error: No suitable PHP function available to execute commands.';
  }
}
