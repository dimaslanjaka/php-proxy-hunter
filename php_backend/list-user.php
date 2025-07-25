<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\UserDB;

global $isCli;

if (!$isCli) {
  // Set CORS (Cross-Origin Resource Sharing) headers to allow requests from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");

  // Set content type to JSON with UTF-8 encoding
  header('Content-Type: application/json; charset=utf-8');

  // Ignore browser caching
  header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
}

$user_db = new UserDB();
$is_admin = ($_SESSION['admin'] ?? false) === true && ($_SESSION['authenticated'] ?? false) === true;
if (!$is_admin) {
  // If the user is not an admin, return an error message
  if (!$isCli) {
    echo json_encode(['error' => 'You are not authorized to view this page.']);
  } else {
    echo 'You are not authorized to view this page.';
  }
  exit;
}

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
    $user['saldo'] = ($user['saldo'] !== null) ? floatval($user['saldo']) : 0; // Format as currency

    // Ensure phone is not null
    $user['phone'] = $user['phone'] ?? 'N/A'; // Default to 'N/A' if NULL

    return $user;
  }, $users);

  remove_array_keys($users, ['password']);
  echo json_encode($users);
}
