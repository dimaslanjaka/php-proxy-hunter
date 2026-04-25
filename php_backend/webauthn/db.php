<?php

require_once __DIR__ . '/../shared.php';

/**
 * Ensure the credentials table exists (supports multiple credentials per user)
 */
function ensure_webauthn_table() {
  global $user_db;
  if (!isset($user_db) || !isset($user_db->db) || !isset($user_db->db->pdo)) {
    return;
  }
  $pdo    = $user_db->db->pdo;
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

  if ($driver === 'sqlite') {
    $sql = 'CREATE TABLE IF NOT EXISTS webauthn_credentials (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_key TEXT NOT NULL,
      credential_id TEXT NOT NULL UNIQUE,
      credential_json TEXT,
      sign_count INTEGER DEFAULT 0,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )';
  } else {
    // mysql
    $sql = 'CREATE TABLE IF NOT EXISTS webauthn_credentials (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      user_key VARCHAR(255) NOT NULL,
      credential_id TEXT NOT NULL UNIQUE,
      credential_json JSON DEFAULT NULL,
      sign_count INT DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
  }
  try {
    $pdo->exec($sql);
  } catch (Throwable $e) {
    // best-effort; don't break flows on table creation errors
  }
}

function db_get_webauthn_credentials(string $userKey) {
  global $user_db;
  ensure_webauthn_table();
  $rows = $user_db->db->select('webauthn_credentials', '*', 'user_key = ?', [$userKey]);
  return $rows ?: [];
}

function db_get_webauthn_credential_by_credential_id(string $credentialId) {
  global $user_db;
  ensure_webauthn_table();
  $rows = $user_db->db->select('webauthn_credentials', '*', 'credential_id = ?', [$credentialId]);
  return isset($rows[0]) ? $rows[0] : null;
}

function db_save_webauthn_credential(string $userKey, string $credentialId_b64url, array $credentialJson, int $sign_count = 0) {
  global $user_db;
  ensure_webauthn_table();
  $pdo    = $user_db->db->pdo;
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  $json   = json_encode($credentialJson, JSON_UNESCAPED_UNICODE);

  if ($driver === 'sqlite') {
    // credential_id is UNIQUE, so INSERT OR REPLACE will update by credential_id
    $sql = 'INSERT OR REPLACE INTO webauthn_credentials (id, user_key, credential_id, credential_json, sign_count, created_at) VALUES (
      COALESCE((SELECT id FROM webauthn_credentials WHERE credential_id = ?), NULL), ?, ?, ?, ?, CURRENT_TIMESTAMP
    )';
    $stmt = $pdo->prepare($sql);
    try {
      $stmt->execute([$credentialId_b64url, $userKey, $credentialId_b64url, $json, $sign_count]);
      return true;
    } catch (Throwable $e) {
      return false;
    }
  } else {
    // MySQL upsert on credential_id unique
    $sql  = 'INSERT INTO webauthn_credentials (user_key, credential_id, credential_json, sign_count) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_key = VALUES(user_key), credential_json = VALUES(credential_json), sign_count = VALUES(sign_count)';
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$userKey, $credentialId_b64url, $json, $sign_count]);
  }
}

function db_delete_webauthn_credential(string $credentialId) {
  global $user_db;
  ensure_webauthn_table();
  try {
    return $user_db->db->delete('webauthn_credentials', 'credential_id = ?', [$credentialId]);
  } catch (Throwable $e) {
    return false;
  }
}
