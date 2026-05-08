<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\Server;

$ip = Server::getClientIp();

respond_json([
  'origin' => $ip,
]);
