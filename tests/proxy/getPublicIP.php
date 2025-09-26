<?php

// require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../func-proxy.php';

// Enable all error reporting for debugging purposes
error_reporting(E_ALL);
ini_set('display_errors', '1');

$result      = getPublicIP(false, 300, ['proxy' => '195.123.243.81:3128'], false, true);
$resultNoSSL = getPublicIP(false, 300, ['proxy' => '195.123.243.81:3128'], true, true);

echo 'Public IP: ' . ($result['ip'] ?? 'N/A') . "\n";
echo 'Public IP (No SSL): ' . ($resultNoSSL['ip'] ?? 'N/A') . "\n";
