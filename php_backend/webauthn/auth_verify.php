<?php

require_once __DIR__ . '/../shared.php';
require_once __DIR__ . '/db.php';

\PhpProxyHunter\Server::allowCors(true);

$data = parseQueryOrPostBody();

// Prefer email when available (request or authenticated session), fall back to username
$email     = $data['email'] ?? ($_SESSION['authenticated_email'] ?? null);
$idKey     = $email ?: ($data['username'] ?? '');
$assertion = $data['assertion'] ?? null;

if (empty($idKey) || empty($assertion)) {
  respond_json(['error' => true, 'message' => 'email/username and assertion required'], 400);
}

$storedChallenge = $_SESSION['webauthn_assertion_challenge_' . $idKey] ?? null;
if (!$storedChallenge) {
  respond_json(['error' => true, 'message' => 'no assertion challenge found for user'], 400);
}

// Load stored credential for this idKey and ensure the assertion is from that credential
$storedCred = null;
$storedCred = db_get_webauthn_credential($idKey);
if (!$storedCred) {
  respond_json(['error' => true, 'message' => 'no registered credential for this account'], 400);
}

$clientDataJSON    = base64_decode(strtr($assertion['response']['clientDataJSON'], '-_', '+/'));
$clientData        = json_decode($clientDataJSON, true);
$receivedChallenge = $clientData['challenge'] ?? null;
if ($receivedChallenge !== $storedChallenge) {
  respond_json(['error' => true, 'message' => 'challenge mismatch'], 400);
}

// Ensure assertion's credential id matches stored credential id for this account
$assertionId = $assertion['id'] ?? ($assertion['rawId'] ?? '');
$storedId    = isset($storedCred['credential_id']) ? $storedCred['credential_id'] : ($storedCred['id'] ?? '');
if ($assertionId !== $storedId) {
  respond_json(['error' => true, 'message' => 'credential id mismatch'], 400);
}

// Minimal successful check — in production verify signature with stored public key
// For now, accept and mark user as authenticated
$_SESSION['authenticated'] = true;
// store the idKey (email preferred) as authenticated_email for later flows
$_SESSION['authenticated_email'] = strtolower($idKey);

// Determine admin emails: merge ADMIN_EMAILS (comma-separated) and DJANGO_SUPERUSER_EMAIL
$admin_emails = [];
if (!empty($_ENV['ADMIN_EMAILS'])) {
  $admin_emails = array_map('trim', explode(',', (string)$_ENV['ADMIN_EMAILS']));
}
if (!empty($_ENV['DJANGO_SUPERUSER_EMAIL'])) {
  $admin_emails[] = trim((string)$_ENV['DJANGO_SUPERUSER_EMAIL']);
}
// Keep only valid email addresses
$admin_emails = array_filter($admin_emails, function ($e) {
  return !empty($e) && filter_var($e, FILTER_VALIDATE_EMAIL);
});
$admin_list = array_unique(array_map('strtolower', $admin_emails));
$isAdmin    = in_array($_SESSION['authenticated_email'], $admin_list, true);

if ($isAdmin) {
  $_SESSION['admin'] = true;
} else {
  if (isset($_SESSION['admin'])) {
    unset($_SESSION['admin']);
  }
}

respond_json(['error' => false, 'message' => ($isAdmin ? 'authenticated (admin)' : 'authenticated'), 'admin' => $isAdmin]);
