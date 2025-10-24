<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\Session as SessionHelper;

PhpProxyHunter\Server::allowCors(true);

global $isCli;

if (!$isCli) {
  header('Content-Type: application/json; charset=utf-8');
}

echo json_encode(['logout' => SessionHelper::clearSessions()]);
