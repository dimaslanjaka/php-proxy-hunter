<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\Session as SessionHelper;

PhpProxyHunter\Server::allowCors(true);

respond_json(['logout' => SessionHelper::clearSessions()]);
