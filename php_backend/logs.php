<?php

require_once __DIR__ . '/shared.php';

// use PhpProxyHunter\LogsRepository; (removed)

global $isAdmin;

PhpProxyHunter\Server::allowCors();
header('Content-Type: text/plain; charset=utf-8');

// $logsRepo instantiation removed
$request = parsePostData(true);
// Allow GET query parameters to override when POST body doesn't provide them
$page = isset($request['page']) ? (int)$request['page'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
if ($page < 1) {
  $page = 1;
}
$perPage = isset($request['per_page']) ? (int)$request['per_page'] : (isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50);
if ($perPage < 1 || $perPage > 500) {
  $perPage = 50;
}

$hash = isset($request['hash']) ? $request['hash'] : '';
if (!empty($hash)) {
  $logFile = tmp() . '/logs/' . $hash . '.txt';
  // Safely read the requested log file. Avoid PHP warnings if file is missing
  // or not readable and provide distinct messages for each case.
  if (file_exists($logFile)) {
    if (is_readable($logFile)) {
      $logData = read_file($logFile);
      if ($logData !== false && $logData !== '') {
        echo $logData;
      } else {
        echo "No logs found for {$hash}." . PHP_EOL;
        echo "Log path: {$logFile}" . PHP_EOL;
      }
    } else {
      echo "Log file exists but is not readable for {$hash}." . PHP_EOL;
      echo "Log path: {$logFile}" . PHP_EOL;
    }
  } else {
    echo "No logs found for {$hash}." . PHP_EOL;
    echo "Log path: {$logFile}" . PHP_EOL;
  }
  exit;
}

// Handle unauthenticated access to own logs
if (empty($_SESSION['authenticated_email'])) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['authenticated' => false, 'error' => true, 'message' => 'Not authenticated'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

if (isset($request['me'])) {
  header('Content-Type: application/json; charset=utf-8');
  if (empty($_SESSION['authenticated_email'])) {
    echo json_encode(['authenticated' => false, 'error' => true, 'message' => 'Not authenticated'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
  }
  $user = $user_db->select($_SESSION['authenticated_email']);
  if (empty($user)) {
    echo json_encode(['authenticated' => false, 'error' => true, 'message' => 'User not found'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Respect pagination for 'me' requests
  $offset = ($page - 1) * $perPage;

  // Fetch a sufficiently large recent set and then filter by user
  // Adjust the limit here if you expect more than 1000 entries per user
  $allLogs  = $log_db->recent(1000);
  $userLogs = array_values(array_filter($allLogs, function ($log) use ($user) {
    $isLogActionByUser = isset($log['user_id']) && $log['user_id'] == $user['id'];
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

  echo json_encode([
    'authenticated' => true,
    'error'         => false,
    'logs'          => $pageLogs,
    'page'          => $page,
    'per_page'      => $perPage,
    'offset'        => $offset,
    'count'         => count($pageLogs),
    'total'         => $total,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}


if ($isAdmin) {
  header('Content-Type: application/json; charset=utf-8');
  // Allow optional GET overrides for admin pagination
  if (isset($_GET['page'])) {
    $page = max(1, intval($_GET['page']));
  }
  if (isset($_GET['per_page'])) {
    $perPage = max(1, min(500, intval($_GET['per_page'])));
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

  echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}
