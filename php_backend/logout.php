<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\Session as SessionHelper;

PhpProxyHunter\Server::allowCors();

global $isCli;

if (!$isCli) {
  header('Content-Type: application/json; charset=utf-8');
  header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
}

echo json_encode(['logout' => SessionHelper::clearSessions()]);
