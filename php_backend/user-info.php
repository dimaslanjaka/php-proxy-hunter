<?php

require_once __DIR__ . '/../func.php';
require_once __DIR__ . '/../func-proxy.php';

use PhpProxyHunter\UserDB;

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

$user_db = new UserDB(tmp() . '/database.sqlite');
$browserId = getUserId();
$request = parsePostData();
$currentScriptFilename = basename(__FILE__, '.php');

$email = !$isCli ? ($_SESSION['authenticated_email'] ?? '') : '';
$from_db = $user_db->select($email);
if (empty($from_db['saldo']) && !empty($from_db['id'])) {
  // Insert saldo when column not exist
  $user_db->update_saldo($from_db['id'], 0);
  // Update variable
  $from_db = $user_db->select($email);
}

$result = [
  'authenticated' => isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true,
  'uid' => $browserId,
  'email' => anonymizeEmail($email),
  'saldo' => intval($from_db['saldo'] ?? null)
];
// Assign admin
if ($isAdmin) {
  $result['admin'] = true;
}
// Sort array keys alphabetically
ksort($result);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
