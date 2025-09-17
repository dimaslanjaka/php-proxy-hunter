<?php

include __DIR__ . '/shared.php';

use PhpProxyHunter\LogsRepository;

global $isAdmin;

// Allow from any origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: *');
header('Content-Type: text/plain; charset=utf-8');

$logsRepo = new LogsRepository($core_db);
$request  = parsePostData(true);
$page     = isset($request['page']) ? (int)$request['page'] : 1;
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

if ($isAdmin) {
  header('Content-Type: application/json; charset=utf-8');
  $offset   = ($page - 1) * $perPage;
  $logs     = $logsRepo->getLogsFromDb($perPage, $offset);
  $response = [
    'page'     => $page,
    'per_page' => $perPage,
    'logs'     => $logs,
    'count'    => count($logs),
  ];
  echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}
