<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
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

$stmt = $pdo->prepare('SELECT * FROM items WHERE updated_at > ?');
$stmt->execute([$since]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);

// Get Items Updated After a Timestamp:
// curl -H "Authorization: Bearer my-secret-token" \
//   "/cloud_sqlite/fetch-updated.php?since=2025-07-07T08:00:00"
