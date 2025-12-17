<?php

require_once __DIR__ . '/../shared.php';

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

// Persist credential for user (simple JSON file storage)
$storeDir  = tmp('webauthn');
$storeFile = $storeDir . '/webauthn_credentials.json';
$all       = [];
if (file_exists($storeFile)) {
  $all = json_decode(file_get_contents($storeFile), true) ?: [];
}

$credId      = $credential['id'] ?? ($credential['rawId'] ?? '');
$all[$idKey] = [
  'id'            => $credId,
  'rawId'         => $credential['rawId'] ?? null,
  'type'          => $credential['type'] ?? 'public-key',
  'response'      => $credential['response'] ?? null,
  'registered_at' => date('c'),
];

// Clean used challenge
file_put_contents($storeFile, json_encode($all, JSON_PRETTY_PRINT));

unset($_SESSION['webauthn_challenge_' . $idKey]);

respond_json(['error' => false, 'message' => 'registered']);
