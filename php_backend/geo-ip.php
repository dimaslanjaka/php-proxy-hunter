<?php

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\GeoIpHelper;
use PhpProxyHunter\Server;

global $isAdmin, $proxy_db;

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
  $cmd          = 'php ' . escapeshellarg(realpath(__DIR__ . '/../geoIp.php')) . ' --str=' . escapeshellarg($extracted[0]->proxy);
  $scriptRunner = tmp() . '/runners/geoIp/' . md5($extracted[0]->proxy) . '.sh';

  // create a small runner wrapper so the detached process can be started reliably
  $isWind = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
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
