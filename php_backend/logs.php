<?php

// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../func.php';
require_once __DIR__ . '/../func-proxy.php';

$hash = $_REQUEST['hash'];
$file = tmp() . '/logs/' . $hash . '.txt';
if (!file_exists($file)) {
  $file = tmp() . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $hash . '.txt';
}
$read = read_file($file);
if ($read) {
  echo $read;
} else {
  echo "No logs found for {$hash}" . PHP_EOL;
}
