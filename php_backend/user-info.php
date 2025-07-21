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

$user_db = new UserDB();
$browserId = getUserId();
$request = parsePostData();

$email = !$isCli ? ($_SESSION['authenticated_email'] ?? '') : '';
$userData = [];

if ($email) {
  $userData = $user_db->select($email);

  if (!isset($userData['saldo']) && isset($userData['id'])) {
    $user_db->update_saldo($userData['id'], 0, basename(__FILE__) . ':' . __LINE__);
    $userData = $user_db->select($email);
  }
}

$result = [
  'authenticated' => !empty($_SESSION['authenticated']),
  'uid'          => $browserId,
  'email'        => $email,
  'saldo'        => (int)($userData['saldo'] ?? 0),
  'username'     => $userData['username'] ?? '',
  'first_name'   => $userData['first_name'] ?? '',
  'last_name'    => $userData['last_name'] ?? '',
];

if (!empty($isAdmin)) {
  $result['admin'] = true;
}

ksort($result);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
