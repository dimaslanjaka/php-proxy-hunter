<?php

require_once __DIR__ . '/../func.php';
include __DIR__ . '/shared.php';

use PhpProxyHunter\LogsRepository;

// Allow from any origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: *');
header('Content-Type: text/plain; charset=utf-8');

$logsRepo = new LogsRepository($core_db);
$request  = parsePostData(true);
$hash     = isset($request['hash']) ? $request['hash'] : '';
if (!empty($hash)) {
  $logData = $logsRepo->getLogsByHash($hash);
  if ($logData) {
    echo $logData;
  } else {
    echo "No logs found for {$hash}" . PHP_EOL;
  }
  exit;
}
