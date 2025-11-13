<?php

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\Server;

global $isAdmin, $proxy_db;

$projectRoot = dirname(__DIR__);

Server::allowCors(true);

// Per-session rate limit: allow this script to be accessed once per 60 seconds
// for non-admin users. The session is started in shared.php. Admins are
// identified by the existing $isAdmin variable (set in shared.php).
if (!$isAdmin && !is_cli()) {
  $key = 'processes_last_access';
  $now = time();
  if (!empty($_SESSION[$key]) && ($now - (int)$_SESSION[$key]) < 60) {
    $wait = 60 - ($now - (int)$_SESSION[$key]);
    // http_response_code(429);
    header('Content-Type: text/plain');
    echo "Too many requests. Please wait {$wait} seconds before retrying." . PHP_EOL;
    exit;
  }
  // mark access time
  $_SESSION[$key] = $now;
}

if (!is_cli()) {
  // header text/plain
  header('Content-Type: text/plain');
}

$userId    = getUserId();
$isWin     = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$processes = [];
if ($isWin) {
  // Windows
  exec('wmic process get ProcessId,ParentProcessId,CommandLine', $output);
  foreach ($output as $line) {
    if (preg_match('/^(.*)\s+(\d+)\s+(\d+)$/', trim($line), $matches)) {
      $commandLine = trim($matches[1]);
      $pid         = (int)$matches[2];
      $ppid        = (int)$matches[3];
      if ($commandLine) {
        // Extract executable (first token), handling quoted paths like "C:\\Program Files\\...\\node.exe"
        $exe = $commandLine;
        if (preg_match('/^"([^"]+)"/', $commandLine, $m)) {
          $exe = $m[1];
        } else {
          // first space-separated token
          $parts = preg_split('/\s+/', $commandLine, 2);
          $exe   = $parts[0];
        }
        // basename
        $basename = basename($exe);
        if (preg_match('/^(?:php(?:\.exe)?|python(?:[0-9\.]*)(?:\.exe)?)$/i', $basename)) {
          $processes[] = [
            'pid'     => $pid,
            'ppid'    => $ppid,
            'command' => trim($commandLine),
          ];
        }
      }
    }
  }
} else {
  // Unix-like
  exec('ps -eo pid,ppid,user,command', $output);
  foreach ($output as $index => $line) {
    if ($index === 0) {
      continue;
    } // Skip header
    if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\S+)\s+(.*)$/', trim($line), $matches)) {
      $pid     = (int)$matches[1];
      $ppid    = (int)$matches[2];
      $user    = $matches[3];
      $command = $matches[4];
      // Extract first token (the executable) and check its basename to avoid matching PATH text
      $exe = $command;
      if (preg_match('/^\s*"([^"]+)"/', $command, $m)) {
        $exe = $m[1];
      } else {
        $parts = preg_split('/\s+/', trim($command), 2);
        $exe   = $parts[0];
      }
      $basename = basename($exe);
      if ($exe && preg_match('/^(?:php(?:\.[a-z0-9]+)?|python(?:[0-9\.]*)?)$/i', $basename)) {
        $processes[] = [
          'pid'     => $pid,
          'ppid'    => $ppid,
          'command' => trim($command),
        ];
      }
    }
  }
}

// Filter processes by user
$processes = array_filter($processes, function ($proc) use ($userId) {
  return strpos($proc['command'], $userId) !== false;
});

if (empty($processes)) {
  echo "No running PHP/Python processes found for user ID: $userId" . PHP_EOL;
  // Define lock file paths with comments preserved
  $lockFiles = [
    // php_backend/proxy-checker.php lock file
    tmp() . '/locks/user-' . $userId . '/php_backend/proxy-checker.lock',
    // php_backend/geoIp.php lock file
    tmp() . '/locks/user-' . $userId . '/geoIp.lock',
  ];

  // Delete each lock file if it exists
  foreach ($lockFiles as $lockFilePath) {
    delete_path($lockFilePath);
  }
} else {
  echo 'Found ' . count($processes) . " running PHP/Python processes for user ID: $userId" . PHP_EOL;
  echo str_repeat('=', 50) . PHP_EOL;
  foreach ($processes as $proc) {
    echo "PID: {$proc['pid']}, PPID: {$proc['ppid']}, Command: {$proc['command']}" . PHP_EOL;
  }
}

// Write working proxies
writing_working_proxies_file($proxy_db);
