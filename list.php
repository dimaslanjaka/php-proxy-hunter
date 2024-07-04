<?php

require_once __DIR__ . '/func-proxy.php';

global $isCli, $isWin;

use PhpProxyHunter\ProxyDB;

if ($isCli) {
  exit('CLI access disallowed');
}

// Set headers to inform the client that the response is JSON and allow CORS (optional)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!$isCli) {
  // Retrieve 'max' and 'page' parameters from the request, with default values
  $max = isset($_GET['max']) ? (int)$_GET['max'] : 10; // default to 10 items per page
  $page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // default to page 1
} else {
  $max = 999;
  $page = 1;
}

$db = new ProxyDB();
$data = $db->getUntestedProxies(10000);

// Calculate the total number of items and pages
$totalItems = count($data);
$totalPages = ceil($totalItems / $max);

// Validate 'page' parameter
if ($page < 1) {
  $page = 1;
} elseif ($page > $totalPages) {
  $page = $totalPages;
}

// Calculate the offset for the current page
$offset = ($page - 1) * $max;

// Slice the data array to get the items for the current page
$pageData = array_slice($data, $offset, $max);

// Prepare the response
$response = [
  "current_page" => $page,
  "total_pages" => $totalPages,
  "total_items" => $totalItems,
  "items_per_page" => $max,
  "items" => $pageData
];

// Output the response as JSON
echo json_encode($response);
