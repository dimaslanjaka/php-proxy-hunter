<?php

declare(strict_types=1);

require_once __DIR__ . '/../func.php';
require_once __DIR__ . '/../func-proxy.php';

use PhpProxyHunter\UserDB;

global $isCli, $isAdmin;

/**
 * Set CORS and response headers for API.
 */
function setHeaders(): void
{
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: *');
  header('Access-Control-Allow-Methods: *');
  header('Content-Type: application/json; charset=utf-8');
  header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
  header('Pragma: no-cache');
}

if (!$isCli) {
  setHeaders();
  $isAdmin = !empty($_SESSION['admin']);
  // Handle preflight OPTIONS request
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
  }
}

$user_db = new UserDB(null, 'mysql', $_ENV['MYSQL_HOST'], $_ENV['MYSQL_DBNAME'], $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASS']);
$browserId = getUserId();
$request = parsePostData();
$result = ['messages' => []];

// Allow updates for authenticated users or admins (admin can update any user by email or username)
if (isset($request['update']) && (!empty($_SESSION['authenticated']) || !empty($isAdmin))) {
  $email = $request['email'] ?? '';
  $username = $request['username'] ?? '';
  $password = $request['password'] ?? '';
  $currentUserData = null;

  if (!empty($isAdmin)) {
    // Admin can update any user by email or username
    if (!empty($email)) {
      $currentUserData = $user_db->select($email);
    } elseif (!empty($username)) {
      $currentUserData = $user_db->select($username);
    }
  } else {
    // Normal user: only allow updating their own account
    $sessionEmail = $_SESSION['authenticated_email'] ?? '';
    if ($email === $sessionEmail || empty($email)) {
      $currentUserData = $user_db->select($sessionEmail);
    }
  }

  if (!empty($currentUserData)) {
    $updateFields = [];
    if (!empty($username) && !empty($isAdmin)) {
      $updateFields['username'] = $username;
    }
    if (!empty($password)) {
      $updateFields['password'] = $password;
    }
    if ($updateFields) {
      $user_db->update($currentUserData['id'], $updateFields);
      $result['success'] = true;
      $result['messages'][] = 'Profile updated successfully.';
    }
  }
  ksort($result);
  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
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

$result += [
  'authenticated' => !empty($_SESSION['authenticated']),
  'uid'           => $browserId,
  'email'         => $email,
  'saldo'         => (int)($userData['saldo'] ?? 0),
  'username'      => $userData['username'] ?? '',
  'first_name'    => $userData['first_name'] ?? '',
  'last_name'     => $userData['last_name'] ?? '',
];
if (!empty($isAdmin)) {
  $result['admin'] = true;
}
ksort($result);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
