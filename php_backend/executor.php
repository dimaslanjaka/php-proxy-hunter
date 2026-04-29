<?php

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\Server;

global $isAdmin, $proxy_db, $isWin;

Server::allowCors(false);
Server::setCacheHeaders(5 * 60);

$isAdmin = is_admin();
$request = parseQueryOrPostBody();

$file = isset($request['file']) ? trim($request['file']) : '';
$str  = isset($request['str']) ? trim($request['str']) : '';
if (empty($str) && isset($request['proxy'])) {
  $str = $request['proxy'];
}
// Sanitize $str so it does not contain literal newlines that would
// break the generated runner shell/batch file. Replace real newlines
// with the two-character sequence "\n" so the argument remains
// a single line in the runner and can be interpreted by the callee.
if (!empty($str)) {
  $str = preg_replace("/\r?\n/", '\\n', $str);
}
$uid = getUserId();
// Allowed executor scripts mapping (key = basename without extension => friendly name)
$executorFiles = [
  'proxy_tun2socks_stability' => 'Tun2Socks Stability Test',
  'proxy_socks5_checker'      => 'SOCKS5 Proxy Checker',
  'proxy-classifier-lookup'   => 'Proxy Classifier Lookup',
  'geoIp.py'                  => 'GeoIP Lookup',
  // php_backend helpers (filename without extension)
  'check-proxy-type'  => 'Check Proxy Type',
  'check-http-proxy'  => 'Check HTTP Proxy',
  'check-https-proxy' => 'Check HTTPS Proxy',
];

// If a specific file was requested, reject early when it matches ignores
if (!empty($file)) {
  // Require a project-root relative path (must start with '/')
  if (strpos($file, '/') !== 0) {
    respond_json(['error' => 'file path must be project-root relative, e.g. /artisan/filename.php'], 400);
  }
  $check        = ltrim($file, '/');
  $parts        = explode('/', $check);
  $isArtisan    = isset($parts[0]) && $parts[0] === 'artisan';
  $isPhpBackend = isset($parts[0]) && $parts[0] === 'php_backend';
  if (!$isArtisan && !$isPhpBackend) {
    respond_json(['error' => 'the requested file is disallowed'], 403);
  }
  $key      = pathinfo($check, PATHINFO_FILENAME);
  $basename = basename($check); // may include extension, e.g. "geoIp.py"
  if (!isset($executorFiles[$key]) && !isset($executorFiles[$basename])) {
    respond_json(['error' => 'the requested file is disallowed'], 403);
  }

  $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  $cmd       = [];
  if ($extension === 'php') {
    $cmd[] = 'php';
  } elseif ($extension === 'py') {
    $cmd[] = escapeshellarg($isWin ? get_project_root('bin', 'py.cmd') : get_project_root('bin', 'py'));
  } else {
    respond_json(['error' => 'unsupported file type'], 400);
  }
  $resolveFile = get_project_root(ltrim($file, '/'));
  if (!file_exists($resolveFile) || !is_file($resolveFile)) {
    respond_json(['error' => 'file not found'], 404);
  }
  $cmd[] = escapeshellarg(realpath($resolveFile));
  if (!empty($str)) {
    // Avoid passing overly long arguments to the shell (escapeshellarg limit on Windows/PHP).
    // If the payload is large, write it to a temporary file and pass --file=<path> instead.
    if (is_string($str) && strlen($str) > 7000) {
      $proxyFile = get_project_root('assets', 'proxies', 'added-executor-' . substr($uid, 0, 8) . '-' . substr(md5($str), 0, 8) . '.txt');
      write_file($proxyFile, $str);
      $cmd[] = '--file=' . escapeshellarg($proxyFile);
    } else {
      $cmd[] = '--str=' . escapeshellarg($str);
      $cmd[] = '--proxy=' . escapeshellarg($str);
    }
  }
  $cmd[]    = '--userId=' . escapeshellarg($uid);
  $cmd[]    = '--uid=' . escapeshellarg($uid);
  $lockFile = tmp('locks', substr(md5(basename($file) . '-' . $uid), 0, 16) . '.lock');
  $cmd[]    = '--lockFile=' . escapeshellarg($lockFile);

  if ($isAdmin) {
    $cmd[] = '--admin=' . escapeshellarg('true');
  }

  $outputFile = tmp('logs', $uid, basename($file) . '.log');
  if (!is_dir(dirname($outputFile))) {
    @mkdir(dirname($outputFile), 0755, true);
  }

  $role      = $isAdmin ? 'admin' : 'user';
  $logHeader = '=== Log for ' . basename($file) . ' (' . $role . ') started at ' . date('Y-m-d H:i:s') . " ===\n\n";
  if ($isAdmin) {
    $logHeader .= 'Command:\n';
    $logHeader .= $cmd[0] . ' ' . $cmd[1] . "\n";
    for ($i = 2; $i < count($cmd); $i++) {
      $arg     = $cmd[$i];
      $display = $arg;
      if (strpos($arg, '--str=') === 0 || strpos($arg, '--proxy=') === 0) {
        $pos    = strpos($arg, '=');
        $prefix = substr($arg, 0, $pos + 1);
        $val    = substr($arg, $pos + 1);
        if (strlen($val) >= 2 && (($val[0] === "'" && $val[strlen($val) - 1] === "'") || ($val[0] === '"' && $val[strlen($val) - 1] === '"'))) {
          $val = substr($val, 1, -1);
        }
        $val = str_replace("\\'", "'", $val);
        if (strlen($val) > 100) {
          $val = substr($val, 0, 100) . '...';
        }
        $display = $prefix . escapeshellarg($val);
      }

      $logHeader .= '  ' . $display . "\n";
    }
  }
  write_file($outputFile, $logHeader);

  $cmd[] = '>>';
  $cmd[] = escapeshellarg($outputFile);
  $cmd[] = '2>&1';

  $runner = tmp('runners', $uid, basename($file) . ($isWin ? '.bat' : '.sh'));

  // Build runner script that mirrors cmd-here.bat PATH setup on Windows
  $workspace  = get_project_root();
  $commandStr = implode(' ', $cmd);

  $PATH      = getenv('PATH');
  $finalPATH = '';

  if ($isWin) {
    // Prepare Windows-style workspace path without trailing backslash
    $workspaceWin = str_replace('/', '\\', rtrim($workspace, '/\\'));
    // Mirror cmd-here.bat CUSTOM_PATH entries and include workspace-specific bins
    // Append the current PHP process PATH into CUSTOM_PATH (sanitized)
    $winPath = '';
    if (!empty($PATH)) {
      $winPath = str_replace('"', '', str_replace('/', '\\', $PATH));
    }
    $customPath = "%LOCALAPPDATA%\\nvm;C:\\nvm4w\\nodejs;C:\\Program Files\\Nox\\bin;D:\\Program Files\\Nox\\bin;C:\\Program Files\\Git\\cmd;C:\\Program Files\\Git\\usr\\bin;%PATH%;{$workspaceWin}\\node_modules\\.bin;{$workspaceWin}\\bin;{$workspaceWin}\\vendor\\bin;C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin;C:\\laragon\\bin\\php\\php-8.4.11-Win32-vs17-x64;C:\\laragon\\bin\\git\\bin;C:\\laragon\\bin\\python\\python-3.13;C:\\laragon\\bin\\memcached\\memcached-1.6.8-win64-mingw";
    if ($winPath !== '') {
      $customPath .= ';' . $winPath;
    }
    $finalPATH = $customPath;

    $script = "@echo off\r\n";
    $script .= "set \"WORKSPACE_FOLDER={$workspaceWin}\"\r\n";
    $script .= "set \"CUSTOM_PATH={$customPath}\"\r\n";
    $script .= "set \"PATH=%CUSTOM_PATH%\"\r\n";
    $script .= $commandStr . "\r\n";
  } else {
    // POSIX: include node_modules/.bin, bin, vendor/bin and project bin dir
    $escapedWorkspace = str_replace('"', '\\"', rtrim($workspace, '/'));
    $posixExtras      = "$escapedWorkspace/node_modules/.bin:$escapedWorkspace/bin:$escapedWorkspace/vendor/bin";
    $binDir           = get_project_root('bin');
    $escapedBin       = str_replace('"', '\\"', $binDir);
    $script           = "#!/usr/bin/env bash\n";
    $script .= "WORKSPACE_FOLDER=\"{$escapedWorkspace}\"\n";
    // Ensure a minimal system PATH is present for webrunner environments
    $finalPATH = "\"/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:{$posixExtras}:{$escapedBin}:$PATH\"";
    $script .= "export PATH=$finalPATH\n";
    $script .= $commandStr . "\n";
  }

  write_file($runner, $script);
  if (!$isWin) {
    @chmod($runner, 0755);
  }
  runBashOrBatch($runner);

  $response = [
    'logFile' => toUnixPath(str_replace(get_project_root(), '', $outputFile)),
  ];

  if ($isAdmin) {
    $response['PATH']    = $finalPATH;
    $response['command'] = implode(' ', $cmd);
    $response['message'] = 'Execution ' . toUnixPath(str_replace(get_project_root(), '', $cmd[0])) . ' ' . toUnixPath(str_replace(get_project_root(), '', $cmd[1])) . ' started.';
  } else {
    $response['message'] = 'Execution started.';
  }

  respond_json($response);
}

if (isset($request['list'])) {
  $files = [];
  foreach ($executorFiles as $key => $label) {
    // Allow executor map keys to include an explicit extension (e.g. "geoIp.py").
    // Prefer artisan .php and .py (show both if present), then php_backend .php.
    $found  = false;
    $hasExt = strpos($key, '.') !== false;

    if ($hasExt) {
      // If the key already contains an extension, prefer that exact filename.
      $artisanExact = get_project_root('artisan', $key);
      if (file_exists($artisanExact) && is_file($artisanExact)) {
        $files[] = ['name' => $label, 'path' => '/artisan/' . $key];
        $found   = true;
      }

      $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
      if (!$found && $ext === 'php') {
        $phpBackendExact = get_project_root('php_backend', $key);
        if (file_exists($phpBackendExact) && is_file($phpBackendExact)) {
          $files[] = ['name' => $label, 'path' => '/php_backend/' . $key];
          $found   = true;
        }
      }

      // If nothing matched using the explicit name, fall back to the original
      // discovery using the base filename (without extension).
      if (!$found) {
        $base       = pathinfo($key, PATHINFO_FILENAME);
        $artisanPhp = get_project_root('artisan', $base . '.php');
        $artisanPy  = get_project_root('artisan', $base . '.py');
        $phpBackend = get_project_root('php_backend', $base . '.php');

        if (file_exists($artisanPhp) && is_file($artisanPhp)) {
          $files[] = ['name' => $label, 'path' => '/artisan/' . $base . '.php'];
          $found   = true;
        }
        if (file_exists($artisanPy) && is_file($artisanPy)) {
          $files[] = ['name' => $label, 'path' => '/artisan/' . $base . '.py'];
          $found   = true;
        }
        if (!$found) {
          if (file_exists($phpBackend) && is_file($phpBackend)) {
            $files[] = ['name' => $label, 'path' => '/php_backend/' . $base . '.php'];
          } else {
            $files[] = ['name' => $label, 'path' => '/artisan/' . $base . '.php'];
          }
        }
      }
    } else {
      // No extension in map key: original behavior
      $artisanPhp = get_project_root('artisan', $key . '.php');
      $artisanPy  = get_project_root('artisan', $key . '.py');
      $phpBackend = get_project_root('php_backend', $key . '.php');

      if (file_exists($artisanPhp) && is_file($artisanPhp)) {
        $files[] = ['name' => $label, 'path' => '/artisan/' . $key . '.php'];
        $found   = true;
      }
      if (file_exists($artisanPy) && is_file($artisanPy)) {
        $files[] = ['name' => $label, 'path' => '/artisan/' . $key . '.py'];
        $found   = true;
      }

      if (!$found) {
        if (file_exists($phpBackend) && is_file($phpBackend)) {
          $files[] = ['name' => $label, 'path' => '/php_backend/' . $key . '.php'];
        } else {
          // fallback to artisan .php path
          $files[] = ['name' => $label, 'path' => '/artisan/' . $key . '.php'];
        }
      }
    }
  }

  respond_json($files);
}
