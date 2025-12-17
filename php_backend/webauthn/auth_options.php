<?php

require_once __DIR__ . '/../shared.php';

\PhpProxyHunter\Server::allowCors(true);

$request = parseQueryOrPostBody();

// Prefer email when available (request or authenticated session), fall back to username
$email = $request['email'] ?? ($_SESSION['authenticated_email'] ?? null);
$idKey = $email ?: ($request['username'] ?? '');

if (empty($idKey)) {
  respond_json(['error' => true, 'message' => 'email or username required'], 400);
}

$challenge     = random_bytes(32);
$challenge_b64 = rtrim(strtr(base64_encode($challenge), '+/', '-_'), '=');

$_SESSION['webauthn_assertion_challenge_' . $idKey] = $challenge_b64;

$storeDir  = tmp('webauthn');
$storeFile = $storeDir . '/webauthn_credentials.json';
$all       = [];
if (file_exists($storeFile)) {
  $all = json_decode(file_get_contents($storeFile), true) ?: [];
}

$allow = [];
if (isset($all[$idKey])) {
  $allow[] = ['type' => 'public-key', 'id' => $all[$idKey]['id']];
} else {
  // No credential registered for this account — don't allow generic assertion prompt
  respond_json(['error' => true, 'message' => 'no credentials registered for this account'], 400);
}

$options = [
  'publicKey' => [
    'challenge'        => $challenge_b64,
    'timeout'          => 60000,
    'rpId'             => ($_ENV['WEBAUTHN_RP_ID'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost')),
    'allowCredentials' => $allow,
    'userVerification' => 'preferred',
  ],
];

respond_json(['error' => false, 'message' => 'ok', 'publicKey' => $options['publicKey']]);
