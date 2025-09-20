<?php

include __DIR__ . '/shared.php';

// use PhpProxyHunter\LogsRepository; (removed)

global $isAdmin;

// Allow from any origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: *');
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
  // $logsRepo usage removed; no logs to return
  echo json_encode([
    'authenticated' => true,
    'error'         => false,
    'logs'          => [],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

if ($isAdmin) {
  header('Content-Type: application/json; charset=utf-8');
  $offset = ($page - 1) * $perPage;
  $logs   = $logsRepo->getLogsFromDb([
    'limit'  => $perPage,
    'offset' => $offset,
  ]);
  $response = [
    'page'     => $page,
    'per_page' => $perPage,
    'logs'     => $logs,
    'count'    => count($logs),
  ];
  echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}
