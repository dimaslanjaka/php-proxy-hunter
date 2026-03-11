<?php

declare(strict_types=1);

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\Server;

// Allow CORS for API usage
Server::allowCors(true);

// Only allow if captcha passed
if (empty($_SESSION['captcha'])) {
  http_response_code(403);
  echo json_encode(['error' => true, 'message' => 'Captcha not verified']);
  exit;
}

$isAuth = is_authenticated(true, $core_db->user_db);
if (!$isAuth) {
  http_response_code(403);
  echo json_encode(['error' => true, 'message' => 'Authentication required']);
  exit;
}

$refresh = refreshDbConnections();
/** @var \PhpProxyHunter\ProxyDB $proxy_db */
$proxy_db = $refresh['proxy_db'];

// helper to parse incoming payload if needed
$request = parseQueryOrPostBody();

header('Content-Type: application/json; charset=utf-8');

try {
  if (empty($request['proxy'])) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Missing required parameter: proxy']);
    exit;
  }

  $proxy = (string)trim($request['proxy']);

  // Attempt to remove the proxy (dont remove from added_proxies)
  try {
    $proxies      = extractProxies($proxy);
    $deleteErrors = [];
    foreach ($proxies as $p) {
      try {
        $proxy_db->remove($p->proxy);
      } catch (Throwable $e) {
        $deleteErrors[] = ['proxy' => $p->proxy, 'detail' => $e->getMessage()];
      }
    }

    if (!empty($deleteErrors)) {
      http_response_code(500);
      echo json_encode([
        'error'   => true,
        'message' => 'Some proxies failed to delete',
        'details' => $deleteErrors,
      ]);
      exit;
    }
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Failed to delete proxy', 'detail' => $e->getMessage()]);
    exit;
  }

  echo json_encode(['error' => false, 'message' => 'Proxy deleted']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
