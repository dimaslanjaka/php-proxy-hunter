<?php

include __DIR__ . '/init.php';

header('Content-Type: application/json');

// Auth check
if ($_SERVER['HTTP_AUTHORIZATION'] ?? '' !== 'Bearer ' . AUTH_TOKEN) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$res = $db->query("SELECT * FROM items");

$data = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
  $data[] = $row;
}

echo json_encode($data);

// Get All Items:
// curl -X GET /cloud_sqlite/fetch.php
