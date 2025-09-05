<?php

require_once __DIR__ . '/../func-proxy.php';

global $isCli, $isWin, $isAdmin;

if (!$isCli) {
  header('Content-Type: text/plain; charset=UTF-8');
}
if (!$isAdmin) {
  exit('Access denied');
}

$scriptPath  = realpath(__DIR__ . '/../proxyChecker.py');
$commandArgs = [
  'max' => '5',
];

$result = runPythonInBackground($scriptPath, $commandArgs, 'proxy-checker-python');

if (isset($result['error'])) {
  echo json_encode(['error' => $result['error']]);
} else {
  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
