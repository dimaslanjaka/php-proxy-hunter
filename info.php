<?php

require_once __DIR__ . '/func.php';

// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header('Content-Type: application/json; charset=utf-8');

echo json_encode(getConfig(getUserId()));
