<?php

require_once __DIR__ . '/func-proxy.php';

global $isCli, $isWin;

use PhpProxyHunter\ProxyDB;

if ($isCli) {
  exit('CLI access disallowed');
}

// Set headers to inform the client and allow CORS (optional)
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
if (isset($_REQUEST['uid'])) {
  setUserId($_REQUEST['uid']);
}
// only allow user with Google Analytics cookie
if (empty($_COOKIE['_ga']) || empty($_SESSION['user_id'])) {
  exit(json_encode(['error' => 'Access Denied']));
}
// check admin
$isAdmin = (!empty($_SESSION['admin']) && $_SESSION['admin'] === true) || is_debug();

$max = 10;
$page = 1;
$status = 'all';
$allowed_status = ['all', 'untested', 'private', 'dead', 'active'];

$parseQueries = parseQueryOrPostBody();

// Retrieve 'max' and 'page' parameters from the request, with default values
$max = isset($parseQueries['max']) ? intval($parseQueries['max']) : 10; // default to 10 items per page
if ($max <= 0) {
  $max = 10;
}
if ($max > 100) {
  $max = 100;
}
$page = isset($parseQueries['page']) ? intval($parseQueries['page']) : 1; // default to page 1
if ($page <= 0) {
  $page = 1;
}
if (!empty($parseQueries['status']) && in_array($parseQueries['status'], $allowed_status)) {
  $status = $parseQueries['status'];
}
if (!empty($parseQueries['format'])) {
  if ($parseQueries['format'] == 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
  }
}
$offset = ($page - 1) * $max;

$db = new ProxyDB();

$params = [];
$whereClause = '';

if ($status !== 'all') {
  $whereClause = 'status = ?';
  $params = [$status];
} elseif ($status == 'private') {
  $whereClause = 'status = ? OR private = ?';
  $params = ['private', 'true'];
}

// Fetch total items for pagination calculation
$totalItems = $db->db->count('proxies', $whereClause, $params);
$totalPages = ceil($totalItems / $max);

$orderByRandom = $max > 0 ? 'ORDER BY RANDOM()' : '';
$query = "SELECT * FROM proxies";
if ($whereClause) {
  $query .= " WHERE $whereClause";
}
if (isset($parseQueries['random'])) {
  $query .= " $orderByRandom";
}
$query .= " ORDER BY last_check DESC LIMIT $max OFFSET $offset";

$data = $db->db->executeCustomQuery($query, $params);

$response = [
  "query" => $isAdmin ? $query : '',
  "current_page" => $page,
  "total_pages" => $totalPages,
  "total_items" => $totalItems,
  "items_per_page" => $max,
  "items" => $data
];

echo json_encode($response, JSON_PRETTY_PRINT);
