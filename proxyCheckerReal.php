<?php

require_once __DIR__ . '/func-proxy.php';

global $isCli;

use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Scheduler;
use PhpProxyHunter\Server;

Scheduler::$debug = false;

// re-check working proxies
// real check whether actual title same

$output_log = __DIR__ . '/proxyChecker.txt';
$max = 100;
$db = new ProxyDB(__DIR__ . '/src/database.sqlite');

// environment checks
if (!$isCli) {
  // set output buffering to zero
  ini_set('output_buffering', 0);
  if (ob_get_level() == 0) {
    ob_start();
  }
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: text/plain; charset=UTF-8');
  // setup lock file
  $id = Server::getRequestIP();
  if (empty($id)) {
    $id = Server::useragent();
  }
  // lock file same as scanPorts.php
  $webLockFile = tmp() . '/runners/real-web-' . sanitizeFilename($id) . '.lock';
  if (file_exists($webLockFile) && !$isAdmin) {
    exit(date(DATE_RFC3339) . ' another process still running (web lock file is locked) ' . basename(__FILE__, '.php') . PHP_EOL);
  } else {
    write_file($webLockFile, date(DATE_RFC3339));
    // truncate output log file
    truncateFile($output_log);
  }
  // delete web lock file after webserver closed
  Scheduler::register(function () use ($webLockFile) {
    delete_path($webLockFile);
  }, 'webserver-close-' . md5(__FILE__));
  // parse post data
  if (isset($_REQUEST['proxy'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      // post data with body key/name proxy
      $parse = parsePostData();
      if ($parse) {
        if (isset($parse['proxy'])) {
          $str = rawurldecode($parse['proxy']);
        } else {
          $str = rawurldecode(json_encode($parse));
        }
      }
    } else {
      // proxyCheckerReal.php.php?proxy=ANY_STRING_CONTAINS_PROXY
      $str = rawurldecode($_REQUEST['proxy']);
    }
  }

  // web server run parallel in background
  // avoid bad response or hangs whole web server
  $file = __FILE__;
  $output_file = __DIR__ . '/proxyChecker.txt';
  $cmd = "php " . escapeshellarg($file);

  $runner = tmp() . "/runners/" . basename($webLockFile . '.lock') . ($isWin ? '.bat' : "");
  $uid = getUserId();
  $cmd .= " --userId=" . escapeshellarg($uid);
  $cmd .= " --lockFile=" . escapeshellarg(unixPath($webLockFile));
  $cmd .= " --runner=" . escapeshellarg(unixPath($runner));
  $cmd .= " --max=" . escapeshellarg("30");
  $cmd .= " --admin=" . escapeshellarg($isAdmin ? 'true' : 'false');

  echo $cmd . "\n\n";

  // Generate the command to run in the background
  $cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($output_file), escapeshellarg($webLockFile));

  // Write the command to the runner script
  write_file($runner, $cmd);

  // Execute the runner script in the background
  runBashOrBatch($runner);

  // Exit the PHP script
  exit;
} else {
  $short_opts = "p:m::";
  $long_opts = [
    "max::",
    "userId::",
    "lockFile::",
    "runner::",
    "admin::"
  ];
  $options = getopt($short_opts, $long_opts);
  $isAdmin = !empty($options['admin']) && $options['admin'] !== 'false';
  if (!$isAdmin) {
    // only apply lock file for non-admin command
    if (!empty($options['lockFile'])) {
      if (file_exists($options['lockFile'])) {
        exit(date(DATE_RFC3339) . ' another process still running (--lockFile is locked) ' . basename(__FILE__, '.php') . PHP_EOL);
      }
      write_file($options['lockFile'], '');
      Scheduler::register(function () use ($options) {
        delete_path($options['lockFile']);
      }, 'release-cli-lock');
    }
  }

  if (!empty($options['runner'])) {
    // remove web server runner after finish
    Scheduler::register(function () use ($options) {
      delete_path($options['runner']);
    }, 'release-runner-script');
  }

  if (!empty($options['max'])) {
    $max_test = intval($options['max']);
    if ($max_test > 0) {
      $max = $max_test;
    }
  }
}

// process
$proxies = $db->getWorkingProxies($max);
if (count($proxies) < 10) {
  $proxies = array_merge($proxies, $db->getUntestedProxies($max));
}
if (count($proxies) < 10) {
  $proxies = array_merge($proxies, $db->getDeadProxies($max));
}

foreach ($proxies as $proxy) {
  realCheck($proxy['proxy']);
}

function getPageTitle($html)
{
  preg_match('/<title>(.*?)<\/title>/', $html, $matches);
  return isset($matches[1]) ? $matches[1] : null;
}

function realCheck($proxy)
{
  global $db;

  $headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
    'Accept-Language: en-US,en;q=0.9',
  ];

  /**
   * List of cURL handles for different proxy types
   * @var {\CurlHandle[]}
   */
  $chs = [
    'socks4' => buildCurl($proxy, 'socks4', 'https://bing.com', $headers),
    'http'   => buildCurl($proxy, 'http', 'https://bing.com', $headers),
    'socks5' => buildCurl($proxy, 'socks5', 'https://bing.com', $headers),
  ];

  // Initiate the multi-cURL handler
  $mh = curl_multi_init();

  // Add the handles to the multi-cURL handler
  foreach ($chs as $ch) {
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_multi_add_handle($mh, $ch);
  }

  $active = null;
  $protocols = [];

  // Execute the multi-cURL requests
  do {
    curl_multi_exec($mh, $active);
    curl_multi_select($mh);
  } while ($active > 0);

  // Retrieve and output the results for each proxy type
  foreach ($chs as $proxyType => $ch) {
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $html = curl_multi_getcontent($ch);
    $title = getPageTitle($html);

    // Example logic to determine proxy status
    if (strpos(strtolower($title), 'bing') !== false) {
      echo "$proxyType://$proxy working. Status code: $statusCode\n";
      $protocols[] = $proxyType;
    } else if (!empty($title)) {
      echo "$proxyType://$proxy dead. Status code: $statusCode. Title: $title\n";
    } else {
      echo "$proxyType://$proxy dead. Status code: $statusCode\n";
    }

    // Remove the handle from the multi-cURL handler
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
  }

  // Close the multi-cURL handler
  curl_multi_close($mh);

  if (!empty($protocols)) {
    $db->updateData($proxy, ['status' => 'active', 'type' => strtolower(implode('-', $protocols))]);
  } else {
    $db->updateStatus($proxy, 'dead');
  }
}

function write_working()
{
  global $db;
  echo "[CHECKER-PARALLEL] writing working proxies" . PHP_EOL;
  $data = parse_working_proxies($db);
  file_put_contents(__DIR__ . '/working.txt', $data['txt']);
  file_put_contents(__DIR__ . '/working.json', json_encode($data['array']));
  file_put_contents(__DIR__ . '/status.json', json_encode($data['counter']));
  return $data;
}

Scheduler::register('write_working', 'z_writing_working_proxies');
