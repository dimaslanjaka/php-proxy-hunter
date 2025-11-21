<?php

require_once __DIR__ . '/../../func-proxy.php';

// Enable all error reporting for debugging purposes
error_reporting(E_ALL);
ini_set('display_errors', '1');

const PROXY   = '23.94.85.180:4689';
const TIMEOUT = 300;

$proxyConfig = ['proxy' => PROXY];

// Test with SSL
$resultSSL = getPublicIP(false, TIMEOUT, $proxyConfig, false, true);
echo 'Public IP (SSL): ' . ($resultSSL ?: 'N/A') . "\n";

// Test without SSL
$resultNoSSL = getPublicIP(false, TIMEOUT, $proxyConfig, true, true);
echo 'Public IP (No SSL): ' . ($resultNoSSL ?: 'N/A') . "\n";
