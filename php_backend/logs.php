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
  $logs = $logsRepo->getLogsFromDb();
  echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}
