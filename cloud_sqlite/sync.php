<?php

include __DIR__ . '/init.php';

header('Content-Type: application/json');

// Auth check
if ($_SERVER['HTTP_AUTHORIZATION'] ?? '' !== 'Bearer ' . AUTH_TOKEN) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'], $data['name'], $data['value'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing fields']);
  exit;
}

// Insert or replace
$stmt = $db->prepare("
  INSERT INTO items (id, name, value, updated_at)
  VALUES (?, ?, ?, CURRENT_TIMESTAMP)
  ON CONFLICT(id) DO UPDATE SET
    name = excluded.name,
    value = excluded.value,
    updated_at = CURRENT_TIMESTAMP
");
$stmt->bindValue(1, $data['id'], SQLITE3_INTEGER);
$stmt->bindValue(2, $data['name'], SQLITE3_TEXT);
$stmt->bindValue(3, $data['value'], SQLITE3_TEXT);
$stmt->execute();

echo json_encode(['status' => 'ok']);

// Insert or Update with Timestamp + Token Check:
// curl -X POST /cloud_sqlite/sync.php \
//   -H "Content-Type: application/json" \
//   -d '{"id":1,"name":"device1","value":"hello world"}'
