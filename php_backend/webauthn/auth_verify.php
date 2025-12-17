<?php

require_once __DIR__ . '/../shared.php';

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
$storeDir  = tmp('webauthn');
$storeFile = $storeDir . '/webauthn_credentials.json';
$all       = [];
if (file_exists($storeFile)) {
  $all = json_decode(file_get_contents($storeFile), true) ?: [];
}
$storedCred = $all[$idKey] ?? null;
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
if ($assertionId !== $storedCred['id']) {
  respond_json(['error' => true, 'message' => 'credential id mismatch'], 400);
}

// Minimal successful check — in production verify signature with stored public key
// For now, accept and mark user as authenticated
$_SESSION['authenticated'] = true;
// store the idKey (email preferred) as authenticated_email for later flows
$_SESSION['authenticated_email'] = $idKey;

respond_json(['error' => false, 'message' => 'authenticated']);
