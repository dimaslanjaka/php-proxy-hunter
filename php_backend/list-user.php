<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\UserDB;

global $isCli;

if (!$isCli) {
  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: application/json; charset=utf-8');
}

$user_db = new UserDB(tmp() . '/database.sqlite');
$is_admin = ($_SESSION['admin'] ?? false) === true && ($_SESSION['authenticated'] ?? false) === true;
if ($is_admin) {
  $users = $user_db->db->select('auth_user', '*');
  remove_array_keys($users, ['password']);
  echo json_encode($users);
} else {
  echo json_encode(['error' => 'unauthorized']);
}
