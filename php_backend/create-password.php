<?php

// Django password creator

require_once __DIR__ . '/../func.php';
require_once __DIR__ . '/../func-proxy.php';

global $isCli, $isAdmin;

if (!$isCli) {
  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: application/json; charset=utf-8');
  // check admin
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

$options = $isCli ? getopt("", [
  "password:"
]) : parseQueryOrPostBody();
$venvDir = realpath(__DIR__ . '/../venv');
$projectDir = realpath(__DIR__ . '/../');

if (PHP_OS === 'WINNT') {
  // Windows platform
  $python = realpath($venvDir . '/Scripts/python.exe');
} else {
  // Unix-like platforms (Linux/macOS)
  $python = realpath($venvDir . '/bin/python');
}

$password = $options['password'] ?? '';

$result = !empty($password) ? php_exec("$python $projectDir/manage.py hash_password $password 2>&1") : "error: password empty";
if (is_string($result)) {
  if (strpos($result, "Hashed password:") !== false) {
    $explode = explode("Hashed password:", $result);
    if (isset($explode[1])) {
      echo json_encode(['result' => trim($explode[1])], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
  } else if (strpos($result, "error:") !== false) {
    $explode = explode("error:", $result);
    if (isset($explode[1])) {
      echo json_encode(['error' => trim($explode[1])], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
  }
}
