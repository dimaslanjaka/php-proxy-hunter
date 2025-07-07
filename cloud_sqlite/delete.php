<?php

include __DIR__ . '/init.php';

header('Content-Type: application/json');

if ($_SERVER['HTTP_AUTHORIZATION'] ?? '' !== 'Bearer ' . AUTH_TOKEN) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing id']);
  exit;
}

$stmt = $db->prepare("DELETE FROM items WHERE id = ?");
$stmt->bindValue(1, $data['id'], SQLITE3_INTEGER);
$stmt->execute();

echo json_encode(['status' => 'deleted']);
