<?php

require_once __DIR__ . '/../func-proxy.php';
require_once __DIR__ . '/shared.php';

global $isCli, $isAdmin, $log_db;

/**
 * Set CORS and response headers for API.
 */
if (!$isCli) {
  PhpProxyHunter\Server::allowCors(true);
  header('Content-Type: application/json; charset=utf-8');
  $isAdmin = !empty($_SESSION['admin']);
}

$browserId = getUserId();
$request   = parsePostData();
$result    = ['messages' => []];

// Allow updates for authenticated users or admins (admin can update any user by email or username)
if (isset($request['update']) && (!empty($_SESSION['authenticated']) || !empty($isAdmin))) {
  $email           = $request['email']    ?? '';
  $username        = $request['username'] ?? '';
  $password        = $request['password'] ?? '';
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
      // Log the update action
      $log_db->log(
        (int)$currentUserData['id'], // userId
        'OTHER',                // actionType (use enum value)
        null,                   // targetId
        null,                   // targetType
        null,                   // targetUserId
        [
          'fields_updated' => array_keys($updateFields),
          'values'         => $updateFields,
        ]
      );
      $result['success']    = true;
      $result['messages'][] = 'Profile updated successfully.';
    }
  }
  ksort($result);
  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// Get user info by email or id if provided (admin only)
if ($isAdmin && !isset($request['update']) && (isset($request['email']) || isset($request['id']))) {
  $email = $request['email'] ?? '';
  $id    = isset($request['id']) && is_numeric($request['id']) ? (int)$request['id'] : 0;
  if (!empty($email)) {
    $userData = $user_db->select($email);
  } elseif ($id > 0) {
    $userData = $user_db->select($id);
  } else {
    $userData = [];
  }
  if (!empty($userData)) {
    $result += [
      'success'       => true,
      'authenticated' => true,
      'uid'           => $userData['id'],
      'email'         => $userData['email'],
      'saldo'         => (int)($userData['saldo'] ?? 0),
      'username'      => $userData['username'] ?? '',
      'first_name'    => $userData['first_name'] ?? '',
      'last_name'     => $userData['last_name'] ?? '',
      'admin'         => $userData['is_superuser'] == 1,
      'staff'         => $userData['is_staff'] == 1,
      'active'        => $userData['is_active'] == 1,
    ];
  } else {
    $result += [
      'success'       => false,
      'authenticated' => false,
      'messages'      => ['User not found.'],
    ];
  }
  ksort($result);
  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

$email    = !$isCli ? ($_SESSION['authenticated_email'] ?? '') : '';
$userData = [];
if ($email) {
  $userData = $user_db->select($email);
  if (!isset($userData['saldo']) && isset($userData['id'])) {
    // Initialize saldo to 0 if not set
    $user_db->updatePoint($userData['id'], 0, basename(__FILE__) . ':' . __LINE__);
    $userData             = $user_db->select($email);
    $result['success']    = true;
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
