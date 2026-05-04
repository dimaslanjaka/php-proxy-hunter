<?php

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\GeoIpHelper;
use PhpProxyHunter\Server;

global $proxy_db;
$isAdmin = is_admin();

Server::allowCors(false);
Server::setCacheHeaders(5 * 60);
// Cache for 5 minutes
header('Content-Type: text/plain; charset=utf-8');

$request   = parseQueryOrPostBody();
$ip        = isset($request['ip']) ? trim($request['ip']) : '';
$extracted = extractProxies($ip);
$isProxy   = !empty($extracted);

if (empty($ip)) {
  respond_json(['error' => true, 'message' => 'No IP address provided.']);
}

if ($isProxy) {
  $ip = explode(':', $extracted[0]->proxy)[0];
  // Run proxy geo lookup in background to avoid blocking
  $cmd = 'php ' . escapeshellarg(__FILE__) . ' --str=' . escapeshellarg($extracted[0]->proxy);

  // prepare runner and output paths (mirror check-https-proxy logic)
  $isWin                 = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  $hash                  = md5($extracted[0]->proxy);
  $currentScriptFilename = basename(__FILE__, '.php');
  $hashFilename          = $currentScriptFilename . '/' . $hash;

  $output_file    = tmp('logs', $hashFilename . '.txt');
  $embedOutputUrl = getFullUrl($output_file);

  // prepare runner script and ensure permissions similar to check-https-proxy
  $file = __FILE__;
  setMultiPermissions([$file, $output_file], true);

  // build proper PHP command and redirect output
  $cmd = getPhpExecutable(true) . ' ' . escapeshellarg($file) . ' --str=' . escapeshellarg($extracted[0]->proxy);
  $cmd = sprintf('%s > %s 2>&1', $cmd, escapeshellarg($output_file));

  $runner = tmp('runners', $hashFilename . ($isWin ? '.bat' : '.sh'));
  write_file($runner, $cmd);

  // execute runner in background using shared helper
  runBashOrBatch($runner);
}

$geo          = GeoIpHelper::getGeoIpSimple($ip);
$isError      = empty($geo['country']) && empty($geo['city']);
$geo['error'] = $isError;
if (!$isAdmin && isset($geo['debug'])) {
  unset($geo['debug']);
}
$geo['ip_queried'] = $ip;
respond_json($geo);
