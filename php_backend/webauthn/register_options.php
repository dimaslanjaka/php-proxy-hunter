<?php

require_once __DIR__ . '/../shared.php';

\PhpProxyHunter\Server::allowCors(true);

// Only allow registration for the currently authenticated session user
$email   = $_SESSION['authenticated_email'] ?? null;
$notAuth = ['error' => true, 'message' => 'not authenticated'];
if (empty($email)) {
  respond_json($notAuth, 401);
}
$idKey = $email;

// Generate challenge (raw bytes then base64url)
$challenge     = random_bytes(32);
$challenge_b64 = rtrim(strtr(base64_encode($challenge), '+/', '-_'), '=');

// Store challenge in session keyed by idKey (email preferred)
$_SESSION['webauthn_challenge_' . $idKey] = $challenge_b64;

// Build a stable user id for WebAuthn from the idKey
$userId = substr(hash('sha256', $idKey), 0, 32);
// fixed-length id
$userId_b64 = rtrim(strtr(base64_encode($userId), '+/', '-_'), '=');

$options = [
  'publicKey' => [
    'challenge'        => $challenge_b64,
    'rp'               => ['name' => $_ENV['WEBAUTHN_RP_NAME'] ?? 'PHP Proxy Hunter', 'id' => ($_ENV['WEBAUTHN_RP_ID'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost'))],
    'user'             => ['id' => $userId_b64, 'name' => $idKey, 'displayName' => ($email ?: $idKey)],
    'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
    'timeout'          => 60000,
    'attestation'      => 'none',
  ],
];

respond_json(['error' => false, 'message' => 'ok', 'publicKey' => $options['publicKey']]);
