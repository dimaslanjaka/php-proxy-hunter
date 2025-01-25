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
  $request = parsePostData(is_debug());
  $user_identifier = $request['id'];
  $amount = floatval($request['amount']);
  $user = $user_db->select($user_identifier);
  if (!empty($user)) {
    $existing_saldo = floatval($user['saldo'] ?? 0);
    $total = $existing_saldo + $amount;
    $user_db->update_saldo($user['id'], $amount, basename(__FILE__) . ":" . __LINE__);
    echo json_encode(['total' => $total, 'existing' => $existing_saldo, 'add' => $amount]);
  } else {
    echo json_encode(['error' => 'user not found']);
  }
} else {
  echo json_encode(['error' => 'unauthorized']);
}
