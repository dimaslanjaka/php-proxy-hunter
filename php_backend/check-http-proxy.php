<?php

include __DIR__ . '/shared.php';

use PhpProxyHunter\Scheduler;
use PhpProxyHunter\Server;

global $isCli;

$url      = 'http://httpforever.com/';
$webTitle = 'HTTP Forever';

$isAdmin = $isCli;

if (!$isCli) {
  // Turn off output buffering
  while (ob_get_level() > 0) {
    ob_end_flush();
  }
  ob_implicit_flush(true);

  PhpProxyHunter\Server::allowCors(true);

  // Set content type to plain text with UTF-8 encoding
  header('Content-Type: text/plain; charset=utf-8');

  // Set user ID from request if available
  $req = parseQueryOrPostBody();
  if (isset($req['uid'])) {
    setUserId($req['uid']);
  }

  if (empty($_SESSION['captcha'])) {
    exit('Access Denied');
  }

  // Check if the user has admin privileges
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

$userId                = getUserId();
$request               = parseQueryOrPostBody();
$currentScriptFilename = basename(__FILE__, '.php');
$full_url              = Server::getCurrentUrl(true);

// Set maximum execution time to [n] seconds
ini_set('max_execution_time', 300);
if (function_exists('set_time_limit')) {
  call_user_func('set_time_limit', 300);
}

if (!$isCli) {
  if (isset($request['proxy'])) {
    $hashFilename  = "$currentScriptFilename-" . $userId;
    $webServerLock = tmp() . "/runners/$hashFilename.proc";

    $proxy = $request['proxy'];

    $proxy_file = tmp() . "/proxies/$hashFilename.txt";
    write_file($proxy_file, $proxy);

    $file        = __FILE__;
    $output_file = tmp() . "/logs/$hashFilename.out";
    setMultiPermissions([$file, $output_file], true);

    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    $cmd = 'php ' . escapeshellarg($file);
    $cmd .= ' --userId=' . escapeshellarg($userId);
    $cmd .= ' --file=' . escapeshellarg($proxy_file);
    $cmd .= ' --admin=' . escapeshellarg($isAdmin ? 'true' : 'false');
    $cmd .= ' --lockFile=' . escapeshellarg(unixPath($webServerLock));
    $cmd = trim($cmd);

    echo $cmd . "\n\n";

    $cmd = sprintf('%s > %s 2>&1', $cmd, escapeshellarg($output_file));

    $runner = tmp() . "/runners/$hashFilename" . ($isWin ? '.bat' : '');
    write_file($runner, $cmd);

    runBashOrBatch($runner);
    exit;
  } else {
    echo 'Usage:' . PHP_EOL;
    echo "\tcurl -X POST $full_url -d \"proxy=72.10.160.171:24049\"" . PHP_EOL;
    exit;
  }
} else {
  $options = getopt('f::p::', ['file::', 'proxy::', 'admin::', 'lockFile::']);
  $isAdmin = !empty($options['admin']) && $options['admin'] !== 'false';

  if (!empty($options['lockFile'])) {
    $lockFile = $options['lockFile'];

    if (!$isAdmin && file_exists($lockFile)) {
      $lockedMsg = date(DATE_RFC3339) . " another process still running ({$lockFile} is locked) ";
      _log($lockedMsg);
      exit($lockedMsg);
    }

    write_file($lockFile, '');

    Scheduler::register(function () use ($lockFile) {
      delete_path($lockFile);
    }, 'release-cli-lock');
  }

  $file = isset($options['f']) ? $options['f'] : (isset($options['file']) ? $options['file'] : null);

  $hashFilename = basename($file, '.txt');
  if ($hashFilename == '.txt' || empty($hashFilename)) {
    $hashFilename = "$currentScriptFilename-cli.txt";
  }

  $proxy = isset($options['p']) ? $options['p'] : (isset($options['proxy']) ? $options['proxy'] : null);

  if (!$file && !$proxy) {
    _log('No proxy file provided. Searching for proxies in database.');

    $proxiesDb = array_merge($proxy_db->getWorkingProxies(100), $proxy_db->getUntestedProxies(100));

    // Filter proxies: keep dead or non-SSL (we only want HTTP testing)
    $filteredArray = array_filter($proxiesDb, function ($item) {
      if (strtolower($item['status']) != 'active') {
        return true;
      }
      return strtolower($item['https']) != 'true';
    });

    $proxyArray = array_map(function ($item) {
      return $item['proxy'];
    }, $filteredArray);

    $proxy = json_encode($proxyArray);
  } elseif ($file) {
    $read = read_file($file);
    if ($read) {
      $proxy = $read;
    }
  }
}

if (empty($hashFilename)) {
  $hashFilename = 'CLI';
}
$lockFolder   = unixPath(tmp() . '/runners/');
$lockFilePath = unixPath($lockFolder . $hashFilename . '.lock');
$lockFiles    = glob($lockFolder . "/$currentScriptFilename*.lock");
if (count($lockFiles) > 2) {
  _log("Proxy checker process limit reached: More than 2 instances of '$currentScriptFilename' are running. Terminating process.");
  exit;
}

$lockFile = fopen($lockFilePath, 'w+');
if ($lockFile === false) {
  throw new RuntimeException("Failed to open or create the lock file: $lockFilePath");
}

$runAllowed = false;

if (flock($lockFile, LOCK_EX)) {
  _log("$lockFilePath Lock acquired");
  $runAllowed = true;

  if (isset($proxy) && !empty($proxy)) {
    truncateFile(get_log_file());
    check($proxy, $url, $webTitle);
  }

  flock($lockFile, LOCK_UN);
  if ($isAdmin) {
    _log("$lockFilePath Lock released");
  } else {
    _log('Lock released');
  }
} else {
  _log('Another process is still running');
  $runAllowed = false;
}

fclose($lockFile);

if ($runAllowed) {
  delete_path($lockFilePath);
}

function get_log_file()
{
  global $hashFilename;
  $_logFile = tmp() . "/logs/$hashFilename.txt";
  if (!file_exists($_logFile)) {
    file_put_contents($_logFile, '');
  }
  setMultiPermissions([$_logFile], true);
  return $_logFile;
}

function _log(...$args): void
{
  global $isCli;
  $_logFile = get_log_file();
  $message  = join(' ', $args) . PHP_EOL;

  append_content_with_lock($_logFile, $message);
  echo $message;
  if (!$isCli) {
    flush();
  }
}

/**
 * Check if the proxy is working (HTTP only)
 * @param string $proxy proxy string or JSON array
 * @param string $url URL to test
 * @param string $webTitle Expected title for the webpage
 */
function check(string $proxy, string $url, string $webTitle)
{
  global $proxy_db, $hashFilename, $currentScriptFilename, $isAdmin, $isCli;
  $proxies = extractProxies($proxy, $proxy_db, true);
  shuffle($proxies);

  $count       = count($proxies);
  $logFilename = str_replace("$currentScriptFilename-", '', $hashFilename);
  _log(trim('[' . ($isCli ? 'CLI' : 'WEB') . '][' . ($isAdmin ? 'admin' : 'user') . '] ' . substr($logFilename, 0, 6) . " Checking $count proxies..."));

  $startTime            = microtime(true);
  $limitSecs            = 120;
  $isExecutionTimeLimit = function () use ($startTime, $limitSecs) {
    $elapsedTime = microtime(true) - $startTime;
    if ($elapsedTime > $limitSecs) {
      _log("Proxy checker execution limit reached {$limitSecs}s.");
      return true;
    }
    return false;
  };

  for ($i = 0; $i < $count; $i++) {
    $no   = $i + 1;
    $item = $proxies[$i];

    if (!$isAdmin && $isExecutionTimeLimit()) {
      break;
    }

    $expired = $item->last_check ? isDateRFC3339OlderThanHours($item->last_check, 5) : true;

    if ($item->status == 'active' && $item->https == 'false' && !$expired) {
      _log("[$no] Skipping proxy {$item->proxy}: Recently checked and non-SSL.");
      continue;
    }
    $protocols = ['http', 'socks4', 'socks5'];
    $latencies = [];
    $http_ok   = false;
    foreach ($protocols as $protocol) {
      $curl = buildCurl($item->proxy, $protocol, $url, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
      ]);
      $result = curl_exec($curl);
      $msg    = "[$no] $protocol://{$item->proxy} ";

      if ($result) {
        $info = curl_getinfo($curl);
        curl_close($curl);
        if ($info['http_code'] == 200) {
          $msg .= round($info['total_time'], 2) . 's ';
          $latencies[] = round($info['total_time'] * 1000, 2);

          if (checkRawHeadersKeywords($result)) {
            $msg .= 'HTTP dead (Azenv). ';
          } else {
            preg_match("/<title>(.*?)<\/title>/is", $result, $matches);
            if (!empty($matches)) {
              $msg .= 'Title: ' . $matches[1];
              if (strtolower($matches[1]) == strtolower($webTitle)) {
                $msg .= ' (VALID) ';
                $http_ok = true;
              } else {
                $msg .= ' (INVALID) ';
              }
            } else {
              $msg .= 'Title: N/A ';
            }
          }
        }
      } else {
        $msg .= 'HTTP dead ';
      }

      _log(trim($msg));
    }

    $data = ['https' => 'false', 'last_check' => date(DATE_RFC3339)];
    if (!empty($latencies)) {
      $data['latency'] = max($latencies);
    }

    // Set status active if any protocol returned a valid title matching the expected web title
    if (!empty($http_ok)) {
      $data['status'] = 'active';
      $data['type']   = 'http';
    }

    $proxy_db->updateData($item->proxy, $data);
  }

  _log('Done checking proxies.');
}
