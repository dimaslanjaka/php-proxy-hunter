<?php

include __DIR__ . '/shared.php';

// use PhpProxyHunter\LogsRepository; (removed)

global $isAdmin;

PhpProxyHunter\Server::allowCors();
header('Content-Type: text/plain; charset=utf-8');

// $logsRepo instantiation removed
$request = parsePostData(true);
$page    = isset($request['page']) ? (int)$request['page'] : 1;
if ($page < 1) {
  $page = 1;
}
$perPage = isset($request['per_page']) ? (int)$request['per_page'] : 50;
if ($perPage < 1 || $perPage > 500) {
  $perPage = 50;
}

$hash = isset($request['hash']) ? $request['hash'] : '';
if (!empty($hash)) {
  $logData = $logsRepo->getLogsByHash($hash);
  if ($logData) {
    echo $logData;
  } else {
    echo "No logs found for {$hash}" . PHP_EOL;
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
  // Get logs for this user from $log_db
  $allLogs  = $log_db->recent(1000);
  $userLogs = array_values(array_filter($allLogs, function ($log) use ($user) {
    $isLogActionByUser  = isset($log['user_id'])        && $log['user_id']        == $user['id'];
    $isLogActionByAdmin = isset($log['target_user_id']) && $log['target_user_id'] == $user['id'];
    return $isLogActionByUser || $isLogActionByAdmin;
  }));
  echo json_encode([
    'authenticated' => true,
    'error'         => false,
    'logs'          => $userLogs,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}


if ($isAdmin) {
  header('Content-Type: application/json; charset=utf-8');
  $allLogs = $log_db->recent($perPage);
  $limit   = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 50;
  $offset  = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
  $logs    = $log_db->recent($limit, $offset);

  // Optionally, include pagination info in the response
  $response = [
    'logs'   => $logs,
    'limit'  => $limit,
    'offset' => $offset,
    'count'  => count($logs),
  ];
  echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}
