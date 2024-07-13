<?php

require_once __DIR__ . '/func-proxy.php';

global $isCli;

use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Server;

$db = new ProxyDB();

$str = '';
$isAdmin = false;

if (!$isCli) {
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: text/plain; charset=utf-8');

  // parse data from web server
  $web_data = null;
  $parseQuery = parseQueryOrPostBody();
  // custom IP
  // ?proxy=123.123.132.123 or Post body key proxy
  if (!empty($parseQuery['proxy'])) {
    $web_data = $parseQuery['proxy'];
  }
  if ($web_data) {
    if (is_array($web_data) || is_object($web_data)) {
      $web_data = json_encode($web_data);
    }
  }
  if (is_string($web_data)) {
    $str = rawurldecode($web_data);
  }
  $isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
} else {
  // parse data from CLI
  $short_opts = "p:m::";
  $long_opts = [
    "proxy:",
    "max::",
    "userId::",
    "lockFile::",
    "runner::",
    "admin::"
  ];
  $options = getopt($short_opts, $long_opts);
  if (!empty($options['proxy'])) {
    $str = $options['proxy'];
  }
  if (!empty($options['admin']) && $options['admin'] === 'true') {
    $isAdmin = true;
  }
}

if (!$isAdmin) {
  // validate lock files
  if (file_exists(__DIR__ . '/proxyChecker.lock') && !is_debug()) {
    exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
  }
}

if (empty($str)) {
  // iterate database and file proxies.txt
  if (file_exists(__DIR__ . '/proxies.txt')) {
    $read = read_first_lines(__DIR__ . '/proxies.txt', 500);
    if ($read) {
      $str = implode("\n", $read);
    }
  }
  if (!$str) {
    $str = '';
  }
  $db->iterateAllProxies(function ($item) use (&$str) {
    $str .= $item['proxy'] . PHP_EOL;
  });
}

iterateLines($str, true, 'execute_line');

$background_running = 0;

function execute_line($line, $line_index)
{
  global $background_running;
  if ($background_running > 30) {
    // skip iterate index 30
    return;
  }
  // extract IPs and generate ports
  $proxies = extractProxies($line, null, false);
  shuffle($proxies);

  // execute all proxies
  foreach ($proxies as $index => $item) {
    if ($index > 30 || $background_running > 30) {
      break;
    }
    $generatedProxies = saveRangePorts($item->proxy);
    do_check($generatedProxies, true);
  }

  // execute random proxies
  // $item = $proxies[array_rand($proxies)];
  // $generatedProxies = saveRangePorts($item->proxy);
  // do_check($generatedProxies);
}

function do_check($filePath, $background = false)
{
  global $isCli, $isAdmin, $background_running;
  $file =  __DIR__ . '/cidr-information/CIDR-check.php';
  $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  $cmd = "php " . escapeshellarg($file);
  $runner = __DIR__ . "/tmp/runners/CIDR-port-checker-" . basename($filePath, '.php') . ($isWin ? '.bat' : ".sh");
  $webLockFile = __DIR__ . '/tmp/' . basename(__FILE__, '.php') . '.lock';
  $output_file = __DIR__ . '/proxyChecker.txt';

  if (!$isCli) {
    $id = Server::getRequestIP();
    if (empty($id)) {
      $id = Server::useragent();
    }
    // lock file same as proxyCheckerParallel.php
    $webLockFile = __DIR__ . '/tmp/runners/scan-port-web-' . sanitizeFilename($id) . '.lock';
    // not admin: skip when existing runner still running
    if (!$isAdmin && file_exists($webLockFile)) {
      echo date(DATE_RFC3339) . ' another process still running' . PHP_EOL;
      return;
    }
    $runner = __DIR__ . "/tmp/runners/" . sanitizeFilename(basename($filePath, '.php') . '-' . basename($webLockFile, '.lock')) . ($isWin ? '.bat' : "");
    $uid = getUserId();
    $cmd .= " --userId=" . escapeshellarg($uid);
    $cmd .= " --lockFile=" . escapeshellarg(unixPath($webLockFile));
  }

  $cmd .= " --runner=" . escapeshellarg(unixPath($runner));
  $cmd .= " --path=" . escapeshellarg($filePath);
  $cmd .= " --max=" . escapeshellarg("500");
  $cmd .= " --admin=" . escapeshellarg($isAdmin ? 'true' : 'false');

  echo $cmd . "\n\n";

  if ($background) {
    // Generate the command to run in the background
    $cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($output_file), escapeshellarg($webLockFile));
    $background_running += 1;
  }

  // Write the command to the runner script
  write_file($runner, $cmd);

  // Ensure runner script has executable permissions
  setMultiPermissions($runner);

  // Output the runner script path for debugging
  // echo escapeshellarg($runner) . PHP_EOL;

  // Execute the runner script in the background
  runBashOrBatch($runner);
}

function saveRangePorts(string $ip)
{
  $explode = explode(":", $ip);
  $ip = $explode[0];
  $outputPath = tmp() . '/ips-ports/' . sanitizeFilename($ip) . '.txt';
  createParentFolders($outputPath);

  // Create generated IP:PORT when output file not found
  if (!file_exists($outputPath)) {
    $proxies = genRangePorts($ip);
    write_file($outputPath, implode("\n", $proxies));
    echo "generated ports written to $outputPath" . PHP_EOL . PHP_EOL;
  }

  // Check if the file size is 0 or if the file contains only whitespace
  if (file_exists($outputPath)) {
    if (filesize($outputPath) === 0 || trim(file_get_contents($outputPath)) === '') {
      unlink($outputPath);
    }
  }

  return $outputPath;
}

function genRangePorts(string $ip)
{
  $min_port = 10;
  $max_port = 65535;

  $proxies = [];
  for ($port = $min_port; $port <= $max_port; $port++) {
    $proxies[] = $ip . ':' . $port;
  }

  return $proxies;
}
