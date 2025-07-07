<?php

include __DIR__ . '/init.php';

header('Content-Type: application/json');

if ($_SERVER['HTTP_AUTHORIZATION'] ?? '' !== 'Bearer ' . AUTH_TOKEN) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$since = $_GET['since'] ?? null;
if (!$since) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing `since` parameter']);
  exit;
}

$stmt = $db->prepare("SELECT * FROM items WHERE updated_at > ?");
$stmt->bindValue(1, $since, SQLITE3_TEXT);
$res = $stmt->execute();

$data = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
  $data[] = $row;
}

echo json_encode($data);

// Get Items Updated After a Timestamp:
// curl -H "Authorization: Bearer my-secret-token" \
//   "/cloud_sqlite/fetch-updated.php?since=2025-07-07T08:00:00"
