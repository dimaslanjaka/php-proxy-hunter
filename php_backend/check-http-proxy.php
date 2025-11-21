<?php

require_once __DIR__ . '/checker-runner.php';

use PhpProxyHunter\Scheduler;
use PhpProxyHunter\Server;

global $isCli;

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
    $hashFilename  = "$currentScriptFilename/$userId";
    $webServerLock = tmp() . "/locks/$hashFilename.lock";

    $proxy = $request['proxy'];

    $proxy_file = tmp() . "/proxies/$hashFilename.txt";
    write_file($proxy_file, $proxy);

    $file        = __FILE__;
    $output_file = tmp() . "/logs/$hashFilename.txt";
    setMultiPermissions([$file, $output_file], true);

    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    $cmd = getPhpExecutable(true) . ' ' . escapeshellarg($file);
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
    respond_text("\tcurl -X POST $full_url -d \"proxy=72.10.160.171:24049\"");
    exit;
  }
} else {
  $options = getopt('f::p::', ['file::', 'proxy::', 'admin::', 'lockFile::']);
  $isAdmin = !empty($options['admin']) && $options['admin'] !== 'false';

  if (!empty($options['lockFile'])) {
    $lockFile = $options['lockFile'];

    if (!$isAdmin && file_exists($lockFile)) {
      $lockedMsg = date(DATE_RFC3339) . " another process still running ({$lockFile} is locked) ";
      _log_shared($hashFilename ?? 'CLI', $lockedMsg);
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
    $hashFilename = "$currentScriptFilename/cli";
  }

  $proxy = isset($options['p']) ? $options['p'] : (isset($options['proxy']) ? $options['proxy'] : null);

  if (!$file && !$proxy) {
    $proxy = load_proxies_for_mode($file, $proxy, 'http', $proxy_db);
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
  _log_shared($hashFilename ?? 'CLI', "Proxy checker process limit reached: More than 2 instances of '$currentScriptFilename' are running. Terminating process.");
  exit;
}

$lockFile = fopen($lockFilePath, 'w+');
if ($lockFile === false) {
  throw new RuntimeException("Failed to open or create the lock file: $lockFilePath");
}

$runAllowed = false;

if (flock($lockFile, LOCK_EX)) {
  _log_shared($hashFilename ?? 'CLI', "$lockFilePath Lock acquired");
  $runAllowed = true;

  if (isset($proxy) && !empty($proxy)) {
    truncateFile(get_log_file_shared($hashFilename ?? 'CLI'));
    check($proxy);
  }

  flock($lockFile, LOCK_UN);
  if ($isAdmin) {
    _log_shared($hashFilename ?? 'CLI', "$lockFilePath Lock released");
  } else {
    _log_shared($hashFilename ?? 'CLI', 'Lock released');
  }
} else {
  _log_shared($hashFilename ?? 'CLI', 'Another process is still running');
  $runAllowed = false;
}

fclose($lockFile);

if ($runAllowed) {
  delete_path($lockFilePath);
}

// logging and log-file helpers are provided by checker-runner.php

/**
 * Check if the proxy is working (HTTP only)
 * @param string $proxy proxy string or JSON array
 */
function check(string $proxy) {
  global $proxy_db, $hashFilename, $isAdmin, $isCli;
  $proxies = extractProxies($proxy, $proxy_db, true);
  shuffle($proxies);

  $count       = count($proxies);
  $logFilename = $hashFilename;
  _log_shared($hashFilename ?? 'CLI', trim('[' . ($isCli ? 'CLI' : 'WEB') . '][' . ($isAdmin ? 'admin' : 'user') . '] ' . substr($logFilename, 0, 6) . " Checking $count proxies..."));

  $startTime            = microtime(true);
  $limitSecs            = 120;
  $isExecutionTimeLimit = function () use ($startTime, $limitSecs, $hashFilename) {
    $elapsedTime = microtime(true) - $startTime;
    if ($elapsedTime > $limitSecs) {
      _log_shared($hashFilename ?? 'CLI', "Proxy checker execution limit reached {$limitSecs}s.");
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
      _log_shared($hashFilename ?? 'CLI', "[$no] Skipping proxy {$item->proxy}: Recently checked and non-SSL.");
      continue;
    }
    // Use the Project's ProxyCheckerHttpOnly class to evaluate the proxy
    $checkerOptions = new \PhpProxyHunter\Checker\CheckerOptions([
      'verbose'   => $isCli ? true : false,
      'timeout'   => 10,
      'protocols' => ['http', 'socks4', 'socks5'],
      'proxy'     => $item->proxy,
    ]);

    // Optionally include auth if present in DB item
    if (!empty($item->username)) {
      $checkerOptions->username = $item->username;
    }
    if (!empty($item->password)) {
      $checkerOptions->password = $item->password;
    }

    $result = \PhpProxyHunter\Checker\ProxyCheckerHttpOnly::check($checkerOptions);

    $data = ['last_check' => date(DATE_RFC3339)];
    if (!empty($result->latency)) {
      $data['latency'] = $result->latency;
    }

    // Build a friendly per-proxy log line
    $statusSymbol = $result->isWorking ? '[OK]' : '[--]';
    $protocols    = !empty($result->workingTypes) ? implode(',', $result->workingTypes) : '';
    $latencyStr   = !empty($result->latency) ? (round($result->latency, 2) . 's') : '';

    $lineParts = ["[$no]", $statusSymbol, $item->proxy];
    if ($protocols !== '') {
      $lineParts[] = 'protocols=' . $protocols;
    }
    if ($latencyStr !== '') {
      $lineParts[] = 'latency=' . $latencyStr;
    }

    _log_shared($hashFilename ?? 'CLI', implode(' ', $lineParts));

    if ($result->isWorking) {
      $data['status'] = 'active';
      // workingTypes already normalized to lowercase elsewhere
      $data['type'] = strtolower(implode('-', array_unique($result->workingTypes)));
    } else {
      // Mark as 'dead' if not working
      // If proxy checked http only and failed, we consider it dead for our purposes
      $data['status'] = 'dead';
    }

    $proxy_db->updateData($item->proxy, $data);
  }

  _log_shared($hashFilename ?? 'CLI', 'Done checking proxies.');
}
