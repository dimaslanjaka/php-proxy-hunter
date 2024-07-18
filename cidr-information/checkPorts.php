<?php

// run CIDR-check.php
// or check open ports by ip

require_once __DIR__ . "/../func-proxy.php";

use PhpProxyHunter\Server;

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
  // checkPorts.php?ip=13.56.192.187:80&ports=440,443,4444,5678
  $ips = extractIPs($parseData['ip']);
  $ports = array_merge($ports, extractPorts($parseData['ip']));
  $customPorts = !empty($parseData['ports']) ? $parseData['ports'] : (!empty($parseData['port']) ? $parseData['port'] : '');
  if (!empty($customPorts)) {
    // force using custom ports
    $ports = extractPorts($customPorts);
  }
}

// unique and shuffling
$ports = array_unique($ports);
$ips = array_unique($ips);
shuffle($ports);
if (!empty($ips)) {
  shuffle($ips);
}

$file = realpath(__DIR__ . '/CIDR-check.php');
$lock_files = [];
$output_file = __DIR__ . '/../proxyChecker.txt';
if (file_exists($output_file)) {
  $output_file = realpath($output_file);
}
setPermissions($output_file, true);
truncateFile($output_file);
$pid_file = tmp() . '/runners/' . basename($file, '.php') . '.pid';
if (file_exists($pid_file)) {
  $pid_file = realpath($pid_file);
}

if (!$isCli) {
  $main_lock_file = tmp() . '/runners/' . sanitizeFilename(Server::getRequestIP()) . ".lock";
  $lock_files[] = $main_lock_file;

  if (file_exists($main_lock_file)) {
    exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
  }
}

$cmd = "php " . escapeshellarg($file);
$uid = getUserId();
$cmd .= " --userId=" . escapeshellarg($uid);
$cmd .= " --max=" . escapeshellarg("30");
$cmd .= " --admin=" . escapeshellarg($isAdmin ? 'true' : 'false');

if (!empty($ips)) {
  sort($ips);
  sort($ports);
  $cmd .= " --ip=" . escapeshellarg(implode(",", array_unique($ips)));
  $cmd .= " --ports=" . escapeshellarg(implode(",", array_unique($ports)));
}

// validate lock files
$lock_file = tmp() . '/runners/' . basename($file, '.php') . '.lock';
$lock_files[] = $lock_file;
if (file_exists($lock_file) && !is_debug()) {
  exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
}

echo $cmd . "\n\n";

if (!$isCli) {
  $cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));

  $runner = tmp() . "/runners/" . basename(__FILE__, '.php') . ($isWin ? '.bat' : "");
  write_file($runner, $cmd);
  write_file($lock_file, '');
  runBashOrBatch($runner);
} else {
  // Open the process file pointer
  $process = popen($cmd, 'r');

  // Check if the process was opened successfully
  if (is_resource($process)) {
    // Read and output the command's output in real-time
    while (!feof($process)) {
      $buffer = fgets($process);
      echo $buffer;
      if (is_output_buffering_active()) {
        // Flush the output buffer to ensure it is displayed immediately
        flush();
        ob_flush();
      }
    }

    // Close the process file pointer
    pclose($process);
  } else {
    echo "Unable to execute command.";
  }
}

function checkIp($ip)
{
  global $ports, $startTime, $maxExecutionTime, $db, $output_file;
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
        $db->updateData($proxy, ['status' => 'untested']);
        echo "$proxy port open" . PHP_EOL;
      } else {
        echo "$proxy port closed" . PHP_EOL;
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

// remove lock files on exit
function exitProcess()
{
  global $lock_files;
  foreach ($lock_files as $file) {
    delete_path($file);
  }
}
register_shutdown_function('exitProcess');
