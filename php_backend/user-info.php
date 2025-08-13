<?php

require_once __DIR__ . '/../func.php';
require_once __DIR__ . '/../func-proxy.php';

use PhpProxyHunter\UserDB;

global $isCli, $isAdmin;

function setHeaders(): void
{
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: application/json; charset=utf-8');
  header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
  header('Pragma: no-cache');
}

if (!$isCli) {
  setHeaders();
  $isAdmin = !empty($_SESSION['admin']);
}

$user_db = new UserDB(null, 'mysql', $_ENV['MYSQL_HOST'], $_ENV['MYSQL_DBNAME'], $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASS']);
$browserId = getUserId();
$request = parsePostData();
$result = ['messages' => []];

if (isset($request['update']) && !empty($_SESSION['authenticated'])) {
  // Update user information
  $email = $request['email'] ?? '';
  $username = $request['username'] ?? '';
  $password = $request['password'] ?? '';
  $currentUserData = $user_db->select($email);
  if (!empty($currentUserData)) {
    if (!empty($username)) {
      $user_db->update($currentUserData['id'], ['username' => $username]);
      $result['success'] = true;
      $result['messages'][] = 'Username updated successfully.';
    }
    if (!empty($password)) {
      $user_db->update($currentUserData['id'], ['password' => $password]);
      $result['success'] = true;
      $result['messages'][] = 'Password updated successfully.';
    }
  }
}

$email = !$isCli ? ($_SESSION['authenticated_email'] ?? '') : '';
$userData = [];

if ($email) {
  $userData = $user_db->select($email);

  if (!isset($userData['saldo']) && isset($userData['id'])) {
    // Initialize saldo to 0 if not set
    $user_db->update_saldo($userData['id'], 0, basename(__FILE__) . ':' . __LINE__);
    $userData = $user_db->select($email);
    $result['success'] = true;
    $result['messages'][] = 'Saldo initialized to 0.';
  }
}

$result['authenticated'] = !empty($_SESSION['authenticated']);
$result['uid'] = $browserId;
$result['email'] = $email;
$result['saldo'] = (int)($userData['saldo'] ?? 0);
$result['username'] = $userData['username'] ?? '';
$result['first_name'] = $userData['first_name'] ?? '';
$result['last_name'] = $userData['last_name'] ?? '';

if (!empty($isAdmin)) {
  $result['admin'] = true;
}

ksort($result);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
