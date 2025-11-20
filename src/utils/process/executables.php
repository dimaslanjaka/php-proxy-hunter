<?php

if (!file_exists(__DIR__ . '/executables.json')) {
  // If the executables.json file does not exist, attempt to generate it
  $nodePath  = 'node';
  $script    = realpath(__DIR__ . '/executables-finder.cjs');
  $output    = null;
  $returnVar = null;

  // Try Node.js script first if Node is available
  $nodeAvailable = (bool) trim(shell_exec(escapeshellcmd($nodePath) . ' -v 2>&1'));
  if ($nodeAvailable && $script && file_exists($script)) {
    exec(escapeshellcmd($nodePath) . ' ' . escapeshellarg($script), $output, $returnVar);
  } else {
    $returnVar = 1;
    // force fallback
  }

  // If Node failed or is not available, generate executables.json using PHP fallback
  if ($returnVar !== 0) {
    generateExecutablesJson();
  }
}

function generateExecutablesJson() {
  // Attempt to locate php and python executables via common locations and PATH
  $root = realpath(__DIR__ . '/../../..');

  $candidates = function ($paths) use ($root) {
    $out = [];
    foreach ($paths as $p) {
      // expand relative paths inside project
      if (strpos($p, '{root}') !== false) {
        $out[] = str_replace('{root}', $root, $p);
      } else {
        $out[] = $p;
      }
    }
    return $out;
  };

  $phpPaths = $candidates([
    PHP_BINARY,
    '/usr/bin/php',
    '/usr/local/bin/php',
    $root . DIRECTORY_SEPARATOR . 'venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php',
    $root . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php',
    'C:\\php\\php.exe',
    'C:\\Program Files\\PHP\\php.exe',
    'C:\\xampp\\php\\php.exe',
  ]);

  $pythonPaths = $candidates([
    '/usr/bin/python3',
    '/usr/local/bin/python3',
    $root . DIRECTORY_SEPARATOR . 'venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
    $root . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
    'C:\\Python39\\python.exe',
    'C:\\Program Files\\Python39\\python.exe',
  ]);

  $isExecutable = function ($p) {
    if (!$p) {
      return false;
    }
    if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
      return file_exists($p);
    }
    return file_exists($p) && is_executable($p);
  };

  $foundPhp = null;
  foreach ($phpPaths as $p) {
    if ($isExecutable($p)) {
      $foundPhp = $p;
      break;
    }
  }
  if (!$foundPhp) {
    // try `which`/`where` fallback
    $whichCmd = stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'where php' : 'which php';
    $whichOut = trim(shell_exec($whichCmd . ' 2>&1'));
    if ($whichOut) {
      $lines    = preg_split('/\r?\n/', trim($whichOut));
      $foundPhp = $lines[0];
    }
  }

  $foundPython = null;
  foreach ($pythonPaths as $p) {
    if ($isExecutable($p)) {
      $foundPython = $p;
      break;
    }
  }
  if (!$foundPython) {
    $whichCmd = stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'where python' : 'which python3';
    $whichOut = trim(shell_exec($whichCmd . ' 2>&1'));
    if ($whichOut) {
      $lines       = preg_split('/\r?\n/', trim($whichOut));
      $foundPython = $lines[0];
    }
  }

  $result = [
    'php'    => $foundPhp ?: null,
    'python' => $foundPython ?: null,
  ];

  $outputFile = __DIR__ . '/executables.json';
  @file_put_contents($outputFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  // if still not created, at least create a minimal file to avoid breaking includes
  if (!file_exists($outputFile)) {
    @file_put_contents($outputFile, json_encode(['php' => null, 'python' => null]));
  }
}

/**
 * Get the configured PHP executable path from executables.json.
 *
 * This function reads the executables.json file located in the same directory,
 * decodes it as JSON and returns the value for the "php" key when present.
 *
 * If the file cannot be read or the "php" key is not present, this function
 * returns null. Note that file_get_contents and json_decode may emit warnings
 * on failure; callers should handle null return values accordingly.
 *
 * @return string|null Absolute path to the PHP executable, or null if not found.
 */
function getPhpExecutable($escape = false) {
  $json    = file_get_contents(__DIR__ . '/executables.json');
  $data    = json_decode($json, true);
  $phpPath = isset($data['php']) ? $data['php'] : null;
  return $escape && $phpPath ? escapeshellcmd($phpPath) : $phpPath;
}

/**
 * Get the configured Python executable path from executables.json.
 *
 * This function reads the executables.json file located in the same directory,
 * decodes it as JSON and returns the value for the "python" key when present.
 *
 * If the file cannot be read or the "python" key is not present, this function
 * returns null. Note that file_get_contents and json_decode may emit warnings
 * on failure; callers should handle null return values accordingly.
 *
 * @return string|null Absolute path to the Python executable, or null if not found.
 */
function getPythonExecutable($escape = false) {
  $json       = file_get_contents(__DIR__ . '/executables.json');
  $data       = json_decode($json, true);
  $pythonPath = isset($data['python']) ? $data['python'] : null;
  return $escape && $pythonPath ? escapeshellcmd($pythonPath) : $pythonPath;
}
