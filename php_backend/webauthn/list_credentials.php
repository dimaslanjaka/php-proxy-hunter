<?php

require_once __DIR__ . '/../shared.php';
require_once __DIR__ . '/db.php';

\PhpProxyHunter\Server::allowCors(true);

$email = $_SESSION['authenticated_email'] ?? null;
if (empty($email)) {
  respond_json(['error' => true, 'message' => 'not authenticated'], 401);
}

$creds = db_get_webauthn_credentials($email);
$out   = [];
foreach ($creds as $c) {
  $out[] = [
    'credential_id' => $c['credential_id'] ?? '',
    'created_at'    => $c['created_at']    ?? null,
    'user_key'      => $c['user_key']      ?? null,
  ];
}

respond_json(['error' => false, 'credentials' => $out]);
