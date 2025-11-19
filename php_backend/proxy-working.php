<?php

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\CoreDB;
use PhpProxyHunter\Server;

global $isAdmin, $dbFile, $dbHost, $dbName, $dbUser, $dbPass, $dbType;

Server::allowCors(true);

if (empty($_SESSION['captcha']) || !$_SESSION['captcha']) {
  respond_json(['error' => true, 'message' => 'Access denied']);
}

$core_db  = new CoreDB($dbFile, $dbHost, $dbName, $dbUser, $dbPass, false, $dbType);
$proxy_db = $core_db->proxy_db;

// Parse query parameters for pagination and ordering
$limitParam     = isset($_GET['limit']) ? (int) $_GET['limit'] : null;
$pageParam      = isset($_GET['page']) ? (int) $_GET['page'] : null;
$perPageParam   = isset($_GET['perPage']) ? (int) $_GET['perPage'] : null;
$randomizeParam = isset($_GET['randomize']) ? $_GET['randomize'] : null;

// Normalize randomize parameter: accept 'true'/'1' as true, 'false'/'0' as false, otherwise null
if ($randomizeParam === 'true' || $randomizeParam === '1') {
  $randomize = true;
} elseif ($randomizeParam === 'false' || $randomizeParam === '0') {
  $randomize = false;
} else {
  $randomize = null;
}

// Safety caps to avoid flooding the server
define('MAX_PER_PAGE', 1000);
define('DEFAULT_PER_PAGE', 100);

// Prepare pagination metadata
$total = $proxy_db->countWorkingProxies();

// Determine effective perPage and page used for response, with caps
if ($pageParam !== null && $perPageParam !== null) {
  $page    = max(1, (int) $pageParam);
  $perPage = max(0, (int) $perPageParam);
} else {
  // Legacy behaviour: when only limit is provided treat it as perPage for page=1
  $perPage = ($limitParam !== null) ? max(0, (int) $limitParam) : null;
  $page    = 1;
}

// If no perPage specified, use a safe default to avoid returning everything
if ($perPage === null) {
  $perPage = DEFAULT_PER_PAGE;
}

// Apply cap to perPage if set and positive
if ($perPage !== null && $perPage > MAX_PER_PAGE) {
  $perPage = MAX_PER_PAGE;
}

// Compute offset to pass to DB (our getWorkingProxies supports page/perPage)
$offset     = null;
$finalLimit = null;
if ($page !== null && $perPage !== null) {
  $offset     = ($page - 1) * $perPage;
  $finalLimit = $perPage;
} else {
  // If only legacy limit provided use it (possibly capped above)
  if ($perPage !== null) {
    $finalLimit = $perPage;
  }
}

// Now call DB method with sanitized values
$workingProxies = $proxy_db->getWorkingProxies($finalLimit, $randomize, $page, $perPage);

$totalPages = ($perPage > 0) ? (int) ceil($total / $perPage) : ($perPage === 0 ? 0 : 1);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'data' => $workingProxies,
  'meta' => [
    'total'      => $total,
    'page'       => $page,
    'perPage'    => $perPage,
    'totalPages' => $totalPages,
  ],
]);
