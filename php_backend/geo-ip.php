<?php

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\GeoIpHelper;
use PhpProxyHunter\Server;

global $proxy_db;

$isAdmin = is_admin();
$isCli   = is_cli();

Server::allowCors(false);
Server::setCacheHeaders(5 * 60); // Cache for 5 minutes
header('Content-Type: text/plain; charset=utf-8');

$request   = parseQueryOrPostBody();
$ip        = trim($request['ip'] ?? '');
$extracted = extractIps($ip);

// expose output file var to global scope (will be set below if proxy found)
$currentScriptFilename = basename(__FILE__, '.php');
$uid                   = getUserId();
$hashFilename          = $currentScriptFilename . '/' . $uid;
$output_file           = tmp('logs', $hashFilename . '.txt');

$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

$runnerResult = [
  'ip_queried' => $ip,
  'logs'       => [],
];

// ---------- VALIDATION ----------
if ($ip === '') {
  respond_json(['error' => true, 'message' => 'No IP address provided.']);
}

// ---------- NON-CLI EXECUTION ----------
if (!empty($extracted) && !$isCli) {
  $ip = $extracted[0];

  // verify output file is valid respond with existing data if so to avoid unnecessary runner executions
  if (file_exists($output_file)) {
    $raw = read_file($output_file);

    if (is_string($raw) && trim($raw) !== '' && ($decoded = json_decode($raw, true)) !== null) {
      if (
        isset($decoded['city'], $decoded['country'], $decoded['ip_queried']) && $decoded['ip_queried'] === $ip
      ) {
        $runnerResult['message'] = 'Using cached result from previous execution.';
        $runnerResult['error']   = false;

        respond_json(array_merge($decoded, $runnerResult));
      }

      $runnerResult['message'] = 'Cached result is invalid or does not match the requested IP. Running new lookup.';
      $runnerResult['error']   = true;
    }
  }

  // prepare runner and output paths (mirror check-https-proxy logic)
  $embedOutputUrl = getFullUrl($output_file);

  // prepare runner script and ensure permissions similar to check-https-proxy
  $file = __FILE__;
  setMultiPermissions([$file, $output_file], true);

  // build proper PHP command and redirect output
  $cmd = sprintf(
    '%s %s --ip=%s > %s 2>&1',
    getPhpExecutable(true),
    escapeshellarg($file),
    escapeshellarg($ip),
    escapeshellarg($output_file)
  );

  if ($isAdmin || is_debug_device()) {
    $runnerResult['logs'][] = "Running command: $cmd";
  }

  $runner = tmp('runners', $hashFilename . ($isWin ? '.bat' : '.sh'));
  write_file($runner, $cmd);

  // execute runner in background using shared helper
  runBashOrBatch($runner);
}

// ---------- CLI EXECUTION ----------
if ($isCli) {
  $geo = GeoIpHelper::getGeoIpSimple($ip);

  $geo['error'] = empty($geo['country']) && empty($geo['city']);

  if (!$isAdmin) {
    unset($geo['debug']);
  }

  $geo['ip_queried'] = $ip;

  write_file($output_file, json_encode($geo));
  respond_json($geo);
}

// ---------- FINAL RESPONSE ----------
$raw  = read_file($output_file);
$data = null;

if (is_string($raw) && trim($raw) !== '') {
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) {
    $data = $decoded;
  }
}

if (!is_array($data)) {
  $data = ['error' => true, 'message' => 'No proxy information found.'];
}

respond_json(array_merge($data, $runnerResult));
