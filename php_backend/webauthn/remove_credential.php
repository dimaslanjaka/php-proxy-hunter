<?php

require_once __DIR__ . '/../shared.php';
require_once __DIR__ . '/db.php';

\PhpProxyHunter\Server::allowCors(true);

$data          = parseQueryOrPostBody();
$credential_id = $data['credential_id']           ?? null;
$email         = $_SESSION['authenticated_email'] ?? null;

if (empty($email) || empty($credential_id)) {
  respond_json(['error' => true, 'message' => 'not authenticated or missing credential_id'], 400);
}

// Attempt to delete the credential owned by this authenticated user in one step.
global $user_db;
if (!isset($user_db) || !isset($user_db->db) || !isset($user_db->db->pdo)) {
  respond_json(['error' => true, 'message' => 'internal db error'], 500);
}
$pdo = $user_db->db->pdo;
try {
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  // Use direct delete with both credential_id and user_key to avoid race conditions
  $sql  = 'DELETE FROM webauthn_credentials WHERE credential_id = ? AND user_key = ?';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$credential_id, $email]);
  $rows = $stmt->rowCount();
  if ($rows > 0) {
    respond_json(['error' => false, 'message' => 'removed']);
  } else {
    // nothing deleted — credential not found for this user
    respond_json(['error' => true, 'message' => 'credential not found'], 404);
  }
} catch (Throwable $e) {
  respond_json(['error' => true, 'message' => 'failed to remove credential'], 500);
}
