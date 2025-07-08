<?php
declare(strict_types=1);

require_once __DIR__ . '/../func.php';
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

// Remove 'auth' from post data if present
$request = function_exists('parsePostData') ? parsePostData(true) : [];
if (isset($request['auth'])) {
  unset($request['auth']);
}

$data = $request;

if (!isset($data['id'], $data['name'], $data['value'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing fields']);
  exit;
}

// Insert or replace
$stmt = $pdo->prepare('
    INSERT INTO items (id, name, value, updated_at)
    VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ON CONFLICT(id) DO UPDATE SET
        name = excluded.name,
        value = excluded.value,
        updated_at = CURRENT_TIMESTAMP
');
$stmt->execute([
    $data['id'],
    $data['name'],
    $data['value'],
]);

echo json_encode(['status' => 'ok']);

// Usage:
// curl -X POST /cloud_sqlite/sync.php \
//   -H "Content-Type: application/json" \
//   -d '{"id":1,"name":"device1","value":"hello world","auth":"YOUR_TOKEN"}'
// or
// curl -X POST 'http://localhost:8000/cloud_sqlite/sync.php?auth=YOUR_TOKEN' \
//   -H "Content-Type: application/json" \
//   -d '{"id":1,"name":"device1","value":"hello world"}'
