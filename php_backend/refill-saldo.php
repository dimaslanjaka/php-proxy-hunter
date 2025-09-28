<?php

include __DIR__ . '/shared.php';

global $isCli, $log_db, $user_db;

if (!$isCli) {
  // Allow from any origin
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: *');
  header('Access-Control-Allow-Methods: *');
  header('Content-Type: application/json; charset=utf-8');
}

$is_admin = ($_SESSION['admin'] ?? false) === true && ($_SESSION['authenticated'] ?? false) === true;

if ($is_admin) {
  $request      = parsePostData(is_debug());
  $user_id      = $request['user'];
  $amount       = floatval($request['amount']);
  $set          = isset($request['set']) ? boolval($request['set']) : false;
  $user         = $user_db->select($user_id);
  $currentAdmin = $user_db->select($_SESSION['authenticated_email'] ?? '');
  if (empty($currentAdmin)) {
    throw new Exception('Current admin user not found in database');
  }
  if (!empty($user)) {
    $existing_saldo = floatval($user['saldo'] ?? 0);
    if ($set) {
      // Set saldo to exact value (replace saldo)
      $user_db->updatePoint($user['id'], $amount, basename(__FILE__) . ':' . __LINE__, '', true);
      $total = $amount;
      $log_db->log(
        $currentAdmin['id'],
        'TOPUP',
        null,
        'topup',
        $user['id'],
        ['amount' => $amount, 'method' => 'admin', 'set' => true, 'existing_saldo' => $existing_saldo, 'total_saldo' => $total]
      );
      echo json_encode(['total' => $total, 'existing' => $existing_saldo, 'set' => $amount]);
    } else {
      // Add saldo (default behavior)
      $user_db->updatePoint($user['id'], $amount, basename(__FILE__) . ':' . __LINE__, '', false);
      $total = $existing_saldo + $amount;
      $log_db->log(
        $currentAdmin['id'],
        'TOPUP',
        null,
        'topup',
        $user['id'],
        ['amount' => $amount, 'method' => 'admin', 'set' => false, 'existing_saldo' => $existing_saldo, 'total_saldo' => $total]
      );
      echo json_encode(['total' => $total, 'existing' => $existing_saldo, 'add' => $amount]);
    }
  } else {
    echo json_encode(['error' => 'user not found']);
  }
} else {
  echo json_encode(['error' => 'unauthorized']);
}
