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

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing id']);
  exit;
}

$stmt = $pdo->prepare('DELETE FROM items WHERE id = ?');
$stmt->execute([$data['id']]);

echo json_encode(['status' => 'deleted']);
