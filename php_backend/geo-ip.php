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
  $isWin       = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  $hash        = md5($extracted[0]->proxy);
  $runner      = tmp('runners', 'geoIp', $hash . ($isWin ? '.bat' : '.sh'));
  $output_file = tmp('logs', 'geoIp', $hash . '.txt');

  // ensure output directory exists
  if (!is_dir(dirname($output_file))) {
    @mkdir(dirname($output_file), 0755, true);
  }

  // create a small runner wrapper so the detached process can be started reliably
  if ($isWin) {
    $runner_content = "@echo off\r\n" . $cmd . ' > ' . escapeshellarg($output_file) . " 2>&1\r\n";
    write_file($runner, $runner_content);
  } else {
    $runner_content = "#!/bin/sh\n" . $cmd . ' > ' . escapeshellarg($output_file) . " 2>&1\n";
    write_file($runner, $runner_content);
    @chmod($runner, 0755);
  }

  // run in background without waiting
  if ($isWin) {
    // Windows: use cmd /C start "" /B to detach process
    $background = 'cmd /C start "" /B ' . escapeshellarg($runner);
    @pclose(@popen($background, 'r'));
  } else {
    // Unix: execute runner script in background
    $background = escapeshellarg($runner) . ' > /dev/null 2>&1 &';
    @exec($background);
  }
}

$geo          = GeoIpHelper::getGeoIpSimple($ip);
$isError      = empty($geo['country']) && empty($geo['city']);
$geo['error'] = $isError;
if (!$isAdmin && isset($geo['debug'])) {
  unset($geo['debug']);
}
$geo['ip_queried'] = $ip;
respond_json($geo);
