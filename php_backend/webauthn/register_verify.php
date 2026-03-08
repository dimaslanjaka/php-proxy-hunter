<?php

require_once __DIR__ . '/../shared.php';
require_once __DIR__ . '/db.php';

\PhpProxyHunter\Server::allowCors(true);

$data = parseQueryOrPostBody();

// Only accept registration for the currently authenticated session user.
$credential = $data['credential']              ?? null;
$idKey      = $_SESSION['authenticated_email'] ?? null;
if (empty($idKey) || empty($credential)) {
  respond_json(['error' => true, 'message' => 'not authenticated or missing credential'], 401);
}

$storedChallenge = $_SESSION['webauthn_challenge_' . $idKey] ?? null;
if (!$storedChallenge) {
  respond_json(['error' => true, 'message' => 'no challenge found for user'], 400);
}

// Minimal verification: check clientDataJSON.challenge matches stored challenge
$clientDataJSON    = base64_decode(strtr($credential['response']['clientDataJSON'], '-_', '+/'));
$clientData        = json_decode($clientDataJSON, true);
$receivedChallenge = $clientData['challenge'] ?? null;
// webauthn-json encodes challenge as base64url inside clientData, so compare
if ($receivedChallenge !== $storedChallenge) {
  respond_json(['error' => true, 'message' => 'challenge mismatch'], 400);
}

// Persist credential for user (DB storage)

$credId = $credential['id'] ?? ($credential['rawId'] ?? '');
// Save credential to DB (store full credential JSON for later verification)
db_save_webauthn_credential($idKey, $credId, $credential, 0);

// Clean used challenge
unset($_SESSION['webauthn_challenge_' . $idKey]);

respond_json(['error' => false, 'message' => 'registered']);
