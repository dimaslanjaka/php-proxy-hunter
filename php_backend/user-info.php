<?php

require_once __DIR__ . '/../func.php';
require_once __DIR__ . '/../func-proxy.php';

use PhpProxyHunter\UserDB;

global $isCli, $isAdmin;

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

  // Check admin
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
  $user_db->update_saldo($from_db['id'], 0, basename(__FILE__) . ":" . __LINE__);
  // Update variable
  $from_db = $user_db->select($email);
}

$result = [
  'authenticated' => !empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true,
  'uid' => $browserId,
  'email' => $email,
  'saldo' => intval($from_db['saldo'] ?? 0),
  'username' => $from_db['username'] ?? '',
  'first_name' => $from_db['first_name'] ?? '',
  'last_name' => $from_db['last_name'] ?? ''
];

// Assign admin
if ($isAdmin) {
  $result['admin'] = true;
}
// Sort array keys alphabetically
ksort($result);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
