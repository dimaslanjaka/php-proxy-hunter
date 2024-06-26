<?php

// run CIDR-check.php
// or check open ports by ip

require_once __DIR__ . "/../func-proxy.php";

global $isCli, $isWin;

header('Content-Type: text/plain; charset=UTF-8');

if (!$isCli) {
  // set output buffering to zero
  ini_set('output_buffering', 0);
  if (ob_get_level() == 0) {
    ob_start();
  }
}

$max = 500; // default max proxies to be checked
$maxExecutionTime = 2 * 60; // 2 mins
$startTime = time();
$isAdmin = is_debug();

if (!$isCli) {
  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: text/plain; charset=utf-8');
  if (isset($_REQUEST['uid'])) {
    setUserId($_REQUEST['uid']);
  }
  // only allow user with Google Analytics cookie
  if (!isset($_COOKIE['_ga'])) {
    exit('Access Denied');
  }
  // check admin
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

$parseData = parseQueryOrPostBody();

$ips = [];
$ports = [
  80, 81, 83, 88, 3128, 3129, 3654, 4444, 5800, 6588, 6666,
  6800, 7004, 8080, 8081, 8082, 8083, 8088, 8118, 8123, 8888,
  9000, 8084, 8085, 9999, 45454, 45554, 53281, 8443
];

if (!empty($parseData['ip'])) {
  // ?ip=IP:PORT
  // OR post body ip with content contains proxies (IP:PORT)
  $ips = extractIPs($parseData['ip']);
  $ports = array_merge($ports, extractPorts($parseData['ip']));
}

shuffle($ports);
if (!empty($ips)) {
  shuffle($ips);
}

$file = realpath(__DIR__ . '/CIDR-check.php');
$lock_files = [];
$output_file = __DIR__ . '/../proxyChecker.txt';
setPermissions($output_file, true);
truncateFile($output_file);
$pid_file = tmp() . '/runners/' . md5($file) . '.pid';

foreach ($ips as $ip) {
  if (isValidIp($ip)) {
    foreach (array_unique($ports) as $port) {
      $port =  intval("$port");
      if (strlen("$port") < 2) {
        continue;
      }
      $timedout = time() - $startTime > $maxExecutionTime;
      if ($timedout) {
        // echo "Execution time exceeded. Stopping execution." . PHP_EOL;
        break;
      }
      $proxy = "$ip:$port";
      if (isPortOpen($proxy)) {
        // add to database on port open
        $date = new DateTime('2014-01-21');
        $format_date = $date->format(DATE_RFC3339);
        $db->updateData($proxy, ['status' => 'untested', 'last_check' => $format_date]);
        echo "$proxy port open" . PHP_EOL;
        append_content_with_lock($output_file, "$proxy port open" . PHP_EOL);
      } else {
        echo "$proxy port closed" . PHP_EOL;
        append_content_with_lock($output_file, "$proxy port closed" . PHP_EOL);
      }
      // flush for live echo
      if (ob_get_level() > 0) {
        // Flush the buffer to the client
        ob_flush();
        // Optionally, you can also flush the PHP internal buffer
        flush();
      }
    }
  }
}

if (empty($ips)) {
  $cmd = "php " . escapeshellarg($file);
  $uid = getUserId();
  $cmd .= " --userId=" . escapeshellarg($uid);
  $cmd .= " --max=" . escapeshellarg("30");
  $cmd .= " --admin=" . escapeshellarg($isAdmin ? 'true' : 'false');

  // validate lock files
  $lock_file = tmp() . '/runners/' . md5($file) . '.lock';
  $lock_files[] = $lock_file;
  if (file_exists($lock_file) && !is_debug()) {
    exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
  }

  echo $cmd . "\n\n";

  $cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));

  $runner = tmp() . "/runners/" . md5(__FILE__) . ($isWin ? '.bat' : "");
  write_file($runner, $cmd);
  write_file($lock_file, '');

  exec(escapeshellarg($runner));
}

// remove lock files on exit
function exitProcess()
{
  global $lock_files;
  foreach ($lock_files as $file) {
    delete_path($file);
  }
}
register_shutdown_function('exitProcess');
