<?php

include __DIR__ . '/shared.php';

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
} else {
  echo 'Found ' . count($processes) . " running PHP/Python processes for user ID: $userId" . PHP_EOL;
  echo str_repeat('=', 50) . PHP_EOL;
  foreach ($processes as $proc) {
    echo "PID: {$proc['pid']}, PPID: {$proc['ppid']}, Command: {$proc['command']}" . PHP_EOL;
  }
}
