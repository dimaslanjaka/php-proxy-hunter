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
  $sql = "SELECT auth_user.id, auth_user.username, auth_user.first_name, auth_user.last_name, auth_user.email, user_fields.saldo, user_fields.phone
        FROM auth_user
        LEFT JOIN user_fields ON auth_user.id = user_fields.user_id;";

  $stmt = $user_db->db->pdo->prepare($sql);
  $stmt->execute();

  // Fetch all results as associative arrays
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $users = array_map(function ($user) {
    // Format saldo as currency or integer
    $user['saldo'] = ($user['saldo'] !== null) ? floatval($user['saldo']) : '0.00'; // Format as currency

    // Ensure phone is not null
    $user['phone'] = $user['phone'] ?? 'N/A'; // Default to 'N/A' if NULL

    return $user;
  }, $users);

  remove_array_keys($users, ['password']);
  echo json_encode($users);
} else {
  echo json_encode(['error' => 'unauthorized']);
}
