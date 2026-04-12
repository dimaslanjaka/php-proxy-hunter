<?php

// Server-side proxy summary counters
// Returns proxy statistics: total, working, private, https, untested, dead

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\Server;

// Allow CORS
Server::allowCors(true);

// Only allow if captcha passed
if (empty($_SESSION['captcha'])) {
  http_response_code(403);
  echo json_encode(['error' => true, 'message' => 'Captcha not verified']);
  exit;
}

$refresh = refreshDbConnections();
/** @var \PhpProxyHunter\ProxyDB $proxy_db */
$proxy_db = $refresh['proxy_db'];

header('Content-Type: application/json; charset=utf-8');

try {
  $response = [
    'error'           => false,
    'counter_proxies' => [
      'total_proxies'    => $proxy_db->countAllProxies(),
      'working_proxies'  => $proxy_db->countWorkingProxies(),
      'private_proxies'  => $proxy_db->countPrivateProxies(),
      'https_proxies'    => $proxy_db->countHttpsProxies(true),
      'untested_proxies' => $proxy_db->countUntestedProxies(),
      'dead_proxies'     => $proxy_db->countDeadProxies(),
    ],
  ];

  echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
