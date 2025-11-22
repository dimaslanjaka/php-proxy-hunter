<?php

require_once __DIR__ . '/../func-proxy.php';

global $isCli, $isWin, $isAdmin;

if (!$isCli) {
  // Allow from any origin
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: *');
  header('Access-Control-Allow-Methods: *');
  header('Content-Type: application/json; charset=utf-8');
  // Set output buffering to zero
  ini_set('output_buffering', 0);
  if (ob_get_level() == 0) {
    ob_start();
  }
  // Need user logged in
  requires_authentication();
}

$parseQuery = parseQueryOrPostBody();
if (!empty($parseQuery['txt'])) {
  header('Content-Type: text/plain; charset=utf-8');
}

$cwd      = realpath(__DIR__ . '/../');
$file     = realpath(__DIR__ . '/../proxyChecker.py');
$venv     = !$isWin ? realpath("$cwd/venv/bin/activate") : realpath("$cwd/venv/Scripts/activate");
$venvCall = $isWin ? "call $venv" : "source $venv";

$commandArgs = '';
foreach ($parseQuery as $key => $value) {
  $escapedValue = escapeshellarg($value);
  $commandArgs .= "--$key=$escapedValue ";
}

// Trim any extra whitespace from the end of $commandArgs
$commandArgs = trim($commandArgs);

// Build filename
$filename = '';
if (!empty($parseQuery['msisdn'])) {
  $filename .= prefixZeroMsisdn(trim(rawurldecode($parseQuery['msisdn'])));
  if (!empty($parseQuery['action'])) {
    $filename .= '-' . trim(rawurldecode($parseQuery['action']));
  }
}
if (empty($filename)) {
  $filename = md5($file . json_encode($parseQuery));
}

$runner      = unixPath(tmp() . "/runners/$filename" . ($isWin ? '.bat' : '.sh'));
$output_file = unixPath(tmp() . "/logs/$filename.txt");
$pid_file    = unixPath(tmp() . "/runners/$filename.pid");

// Truncate output file
truncateFile($output_file);

// Disable directory listing
write_file(tmp() . '/logs/index.html', '');

// Construct the command
$cmd = "$venvCall && python $file $commandArgs > $output_file 2>&1 & echo $! > $pid_file";
$cmd = trim($cmd);

// Write command to runner script
$write = write_file($runner, $cmd);
if (!$write) {
  exit(json_encode(['error' => 'failed writing shell ' . $runner]));
}

// Change current working directory
chdir($cwd);

// Execute the runner script
if ($isWin) {
  $runner_win = 'start /B "window_name" ' . escapeshellarg(unixPath($runner));
  pclose(popen($runner_win, 'r'));
} else {
  exec('bash ' . escapeshellarg($runner) . ' > /dev/null 2>&1 &');
}

$result = [
  'output'   => unixPath($output_file),
  'cwd'      => unixPath($cwd),
  'relative' => str_replace(unixPath($cwd), '', unixPath($output_file)),
];

if ($isAdmin) {
  $result['cmd']    = $cmd;
  $result['PATH']   = getenv('PATH');
  $result['runner'] = $runner;
}

echo json_encode($result);

function prefixZeroMsisdn($msisdn) {
  // Remove any non-digit characters except '+'
  $msisdn = preg_replace('/[^0-9+]/', '', $msisdn);
  if (substr($msisdn, 0, 3) === '+62') {
    $msisdn = '0' . substr($msisdn, 3);
  } elseif (substr($msisdn, 0, 2) === '62') {
    $msisdn = '0' . substr($msisdn, 2);
  }
  return $msisdn;
}
