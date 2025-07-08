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

$stmt = $pdo->query('SELECT * FROM items');
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);

// Get All Items:
// curl -X GET /cloud_sqlite/fetch.php
