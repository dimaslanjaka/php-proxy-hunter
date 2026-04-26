<?php

require_once __DIR__ . '/shared.php';

global $isAdmin;

PhpProxyHunter\Server::allowCors();

$isAdmin = is_admin();
$request = parsePostData(true);
// Allow GET query parameters to override when POST body doesn't provide them
$page = isset($request['page']) ? (int)$request['page'] : (isset($request['page']) ? (int)$request['page'] : 1);
if ($page < 1) {
  $page = 1;
}
$perPage = isset($request['per_page']) ? (int)$request['per_page'] : (isset($request['per_page']) ? (int)$request['per_page'] : 50);
if ($perPage < 1 || $perPage > 500) {
  $perPage = 50;
}

$hash = isset($request['hash']) ? $request['hash'] : '';
if (!empty($hash)) {
  $logFile = tmp('logs', $hash . '.txt');
  // Safely read the requested log file. Avoid PHP warnings if file is missing
  // or not readable and provide distinct messages for each case.
  if (file_exists($logFile)) {
    if (is_readable($logFile)) {
      $logData = read_file($logFile);
      if ($logData !== false && !empty($logData)) {
        echo $logData;
      } else {
        echo "No logs found for {$hash}." . PHP_EOL;
        if ($isAdmin) {
          echo "Log path: {$logFile}" . PHP_EOL;
        }
      }
    } else {
      echo "Log file exists but is not readable for {$hash}." . PHP_EOL;
      if ($isAdmin) {
        echo "Log path: {$logFile}" . PHP_EOL;
      }
    }
  } else {
    echo "No logs found for {$hash}." . PHP_EOL;
    if ($isAdmin) {
      echo "Log path: {$logFile}" . PHP_EOL;
    }
  }
  exit;
}

// Allow unauthenticated access to executor logs
if (isset($request['executor'])) {
  // If a specific file is requested, return its contents (only within user's logs dir)
  $uid        = getUserId();
  $folderUser = tmp('logs', $uid);
  if (isset($request['file'])) {
    // Only accept a filename (basename). Do not allow absolute paths.
    $requestedName = basename((string)$request['file']);

    // Only allow typical log extensions
    $allowedExt = ['log', 'txt'];
    $ext        = strtolower(pathinfo($requestedName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
      respond_text('Invalid log file requested', 400);
    }

    $logPath = tmp('logs', $uid, $requestedName);
    if (!file_exists($logPath) || !is_file($logPath) || !is_readable($logPath)) {
      respond_text('Log file not found or not readable', 404);
    }

    $data = read_file($logPath);
    if ($data === false) {
      respond_text('Failed to read log file', 500);
    }
    respond_text($data);
  }

  if (isset($request['clear'])) {
    // Clear all logs for the user
    if (is_dir($folderUser)) {
      $files = glob($folderUser . '/*.{txt,log}', GLOB_BRACE);
      foreach ($files as $file) {
        @unlink($file);
      }
    }
    respond_json(['cleared' => true]);
  }

  // Otherwise, list log files for the user
  $userLogs = [];
  if (is_dir($folderUser)) {
    $files = glob($folderUser . '/*.{txt,log}', GLOB_BRACE);
    foreach ($files as $file) {
      $userLogs[] = [
        'name'  => basename($file),
        'size'  => human_filesize(filesize($file)),
        'mtime' => filemtime($file),
      ];
    }
  }
  respond_json($userLogs);
}

// Handle unauthenticated access to own logs
if (empty($_SESSION['authenticated_email'])) {
  respond_json(['authenticated' => false, 'error' => true, 'message' => 'Not authenticated']);
}



if (isset($request['me'])) {
  if (empty($_SESSION['authenticated_email'])) {
    respond_json(['authenticated' => false, 'error' => true, 'message' => 'Not authenticated']);
  }
  $user = $user_db->select($_SESSION['authenticated_email']);
  if (empty($user)) {
    respond_json(['authenticated' => false, 'error' => true, 'message' => 'User not found']);
  }

  // Respect pagination for 'me' requests
  $offset = ($page - 1) * $perPage;

  // Fetch a sufficiently large recent set and then filter by user
  // Adjust the limit here if you expect more than 1000 entries per user
  $allLogs  = $log_db->recent(1000);
  $userLogs = array_values(array_filter($allLogs, function ($log) use ($user) {
    $isLogActionByUser  = isset($log['user_id'])        && $log['user_id']        == $user['id'];
    $isLogActionByAdmin = isset($log['target_user_id']) && $log['target_user_id'] == $user['id'];
    return $isLogActionByUser || $isLogActionByAdmin;
  }));

  $total = count($userLogs);

  // slice according to requested page/per_page; this will produce an empty array when offset beyond data
  $pageLogs = $total > 0 ? array_slice($userLogs, $offset, $perPage) : [];
  // decode details JSON for each log entry
  $pageLogs = array_map(function ($log) {
    if (isset($log['details']) && is_string($log['details'])) {
      $decoded = json_decode($log['details'], true);
      if (json_last_error() === JSON_ERROR_NONE) {
        $log['details'] = $decoded;
      }
    }
    return $log;
  }, $pageLogs);

  respond_json([
    'authenticated' => true,
    'error'         => false,
    'logs'          => $pageLogs,
    'page'          => $page,
    'per_page'      => $perPage,
    'offset'        => $offset,
    'count'         => count($pageLogs),
    'total'         => $total,
  ]);
}

if ($isAdmin) {
  // crontab logs
  if (isset($request['cron'])) {
    if (isset($request['file'])) {
      $requestedFile = basename($request['file']);
      $logPath       = tmp('logs', 'crontab', $requestedFile);
      if (file_exists($logPath) && is_readable($logPath)) {
        $logData = read_file($logPath);
        if ($logData !== false) {
          respond_text($logData);
        } else {
          respond_text("Failed to read log file: {$requestedFile}", 500);
        }
      } else {
        respond_text("Log file not found or not readable: {$requestedFile}", 404);
      }
    }
    $cronDir  = tmp('logs', 'crontab');
    $cronLogs = [];
    if (is_dir($cronDir)) {
      $files = glob($cronDir . '/*.{txt,log}', GLOB_BRACE);
      foreach ($files as $file) {
        $cronLogs[] = [
          'name'  => basename($file),
          'path'  => $file,
          'size'  => human_filesize(filesize($file)),
          'mtime' => filemtime($file),
        ];
      }
    }

    respond_json(['logs' => $cronLogs]);
  }
  // Allow optional GET overrides for admin pagination
  if (isset($request['page'])) {
    $page = max(1, intval($request['page']));
  }
  if (isset($request['per_page'])) {
    $perPage = max(1, min(500, intval($request['per_page'])));
  }

  $offset = ($page - 1) * $perPage;
  $logs   = $log_db->recent($perPage, $offset);

  $response = [
    'logs'     => $logs,
    'page'     => $page,
    'per_page' => $perPage,
    'offset'   => $offset,
    'count'    => count($logs),
  ];

  respond_json($response);
}
