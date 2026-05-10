<?php

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\Server;

$isAdmin     = is_admin();
$proxy_db    = refreshDbConnections()['proxy_db'] ?? null;
$projectRoot = dirname(__DIR__);

function format_bytes(float $bytes): string {
  if ($bytes < 1024) {
    return number_format($bytes, 0) . ' B';
  }

  $units = ['KB', 'MB', 'GB', 'TB'];
  $value = $bytes / 1024;
  foreach ($units as $unit) {
    if ($value < 1024 || $unit === 'TB') {
      return rtrim(rtrim(number_format($value, 2), '0'), '.') . ' ' . $unit;
    }
    $value /= 1024;
  }

  return rtrim(rtrim(number_format($value, 2), '0'), '.') . ' TB';
}

Server::allowCors(true);

if (!is_cli()) {
  // header text/plain
  header('Content-Type: text/plain');
}

$userId    = getUserId();
$isWin     = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$processes = [];
if ($isWin) {
  // Windows
  $command = 'powershell -NoProfile -Command "$processes = Get-CimInstance Win32_Process | Select-Object ProcessId,ParentProcessId,CommandLine,WorkingSetSize; $usage = Get-CimInstance Win32_PerfFormattedData_PerfProc_Process | Select-Object IDProcess,PercentProcessorTime; $usageMap = @{}; foreach ($item in $usage) { if ($null -ne $item.IDProcess) { $usageMap[[int]$item.IDProcess] = [double]$item.PercentProcessorTime } }; $processes | ForEach-Object { [pscustomobject]@{ ProcessId = $_.ProcessId; ParentProcessId = $_.ParentProcessId; CommandLine = $_.CommandLine; WorkingSetSize = $_.WorkingSetSize; CpuPercent = $(if ($usageMap.ContainsKey([int]$_.ProcessId)) { $usageMap[[int]$_.ProcessId] } else { 0 }) } } | ConvertTo-Json -Compress"';
  exec($command, $output);
  $json = trim(implode('', $output));
  $rows = $json !== '' ? json_decode($json, true) : [];
  if (isset($rows['ProcessId'])) {
    $rows = [$rows];
  }
  if (!is_array($rows)) {
    $rows = [];
  }
  foreach ($rows as $row) {
    $commandLine = isset($row['CommandLine']) ? trim((string)$row['CommandLine']) : '';
    $pid         = isset($row['ProcessId']) ? (int)$row['ProcessId'] : 0;
    $ppid        = isset($row['ParentProcessId']) ? (int)$row['ParentProcessId'] : 0;
    $workingSet  = isset($row['WorkingSetSize']) ? (float)$row['WorkingSetSize'] : 0.0;
    $cpuPercent  = isset($row['CpuPercent']) ? (float)$row['CpuPercent'] : 0.0;
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
          'pid'            => $pid,
          'ppid'           => $ppid,
          'command'        => $commandLine,
          'cpu_percent'    => $cpuPercent,
          'memory_percent' => 0.0,
          'ram_bytes'      => $workingSet,
        ];
      }
    }
  }
} else {
  // Unix-like
  exec('ps -eo pid,ppid,user,%cpu,%mem,rss,command', $output);
  foreach ($output as $index => $line) {
    if ($index === 0) {
      continue;
    } // Skip header
    if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\S+)\s+([\d.]+)\s+([\d.]+)\s+(\d+)\s+(.*)$/', trim($line), $matches)) {
      $pid     = (int)$matches[1];
      $ppid    = (int)$matches[2];
      $user    = $matches[3];
      $cpuPct  = (float)$matches[4];
      $memPct  = (float)$matches[5];
      $rssKiB  = (float)$matches[6];
      $command = $matches[7];
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
          'pid'            => $pid,
          'ppid'           => $ppid,
          'command'        => trim($command),
          'cpu_percent'    => $cpuPct,
          'memory_percent' => $memPct,
          'ram_bytes'      => $rssKiB * 1024,
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
    tmp('locks', 'user-' . $userId . '/php_backend/proxy-checker.lock'),
    // php_backend/geoIp.php lock file
    tmp('locks', 'user-' . $userId . '/geoIp.lock'),
  ];

  // Delete each lock file if it exists
  foreach ($lockFiles as $lockFilePath) {
    delete_path($lockFilePath);
  }
} else {
  echo 'Found ' . count($processes) . " running PHP/Python processes for user ID: $userId" . PHP_EOL;
  echo str_repeat('=', 50) . PHP_EOL;
  foreach ($processes as $proc) {
    if ($isWin) {
      echo "PID: {$proc['pid']}, PPID: {$proc['ppid']}, CPU: " . number_format((float)$proc['cpu_percent'], 1) . '%, RAM: ' . format_bytes((float)$proc['ram_bytes']) . ", Command: {$proc['command']}" . PHP_EOL;
      continue;
    }

    echo "PID: {$proc['pid']}, PPID: {$proc['ppid']}, CPU: " . number_format((float)$proc['cpu_percent'], 1) . '%, RAM: ' . format_bytes((float)$proc['ram_bytes']) . ' (' . number_format((float)$proc['memory_percent'], 1) . "%), Command: {$proc['command']}" . PHP_EOL;
  }
}
