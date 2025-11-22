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

  // Allow cross-origin requests
  PhpProxyHunter\Server::allowCors(true);

  // Set content type to plain text with UTF-8 encoding
  header('Content-Type: text/plain; charset=utf-8');

  // Parse request body or query string
  $req = parseQueryOrPostBody();
  if (isset($req['uid'])) {
    // Set user ID if provided
    setUserId($req['uid']);
  }

  // Deny access if captcha session is empty
  if (empty($_SESSION['captcha'])) {
    exit('Access Denied');
  }

  // Determine if the user is admin
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

$userId                = getUserId();
$request               = parseQueryOrPostBody();
$currentScriptFilename = basename(__FILE__, '.php');
$full_url              = Server::getCurrentUrl(true);

// Set maximum execution time to 300 seconds
ini_set('max_execution_time', 300);
if (function_exists('set_time_limit')) {
  call_user_func('set_time_limit', 300);
}

if (!$isCli) {
  if (isset($request['proxy'])) {
    // Generate hash filename using current script and user ID
    $hashFilename = "$currentScriptFilename/$userId";
    // Lock file path for web server execution
    $webServerLock = tmp() . "/locks/$hashFilename.lock";
    // Stop if lock file exists (another process running)
    if (file_exists($webServerLock)) {
      respond_json(['error' => true, 'message' => '[HTTP] Another process is still running. Please try again later.']);
    }

    // Get proxy string from request
    $proxy = $request['proxy'];

    // Save proxy to a temporary file
    $proxy_file = tmp() . "/proxies/$hashFilename.txt";
    write_file($proxy_file, $proxy);

    // Prepare output file and set permissions
    $file        = __FILE__;
    $output_file = tmp() . "/logs/$hashFilename.txt";
    setMultiPermissions([$file, $output_file], true);

    // Determine if running on Windows
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    // Build the PHP command to run in background
    $cmd = getPhpExecutable(true) . ' ' . escapeshellarg($file);
    $cmd .= ' --userId=' . escapeshellarg($userId);
    $cmd .= ' --file=' . escapeshellarg($proxy_file);
    $cmd .= ' --admin=' . escapeshellarg($isAdmin ? 'true' : 'false');
    $cmd .= ' --lockFile=' . escapeshellarg(unixPath($webServerLock));
    $cmd = trim($cmd);

    // Redirect stdout and stderr to log file
    $cmd = sprintf('%s > %s 2>&1', $cmd, escapeshellarg($output_file));

    // Create a runner script for the command
    $runner = tmp() . "/runners/$hashFilename" . ($isWin ? '.bat' : '');
    write_file($runner, $cmd);

    // Execute the runner script in background
    runBashOrBatch($runner);

    respond_json(['error' => false, 'message' => '[HTTP] Proxy check initiated.']);
  } else {
    // Show usage instructions for web access
    echo 'Usage:' . PHP_EOL;
    respond_text("\tcurl -X POST $full_url -d \"proxy=72.10.160.171:24049\"");
    exit;
  }
} else {
  // Parse CLI options
  $options = getopt('f::p::', ['file::', 'proxy::', 'admin::', 'lockFile::']);
  $isAdmin = !empty($options['admin']) && $options['admin'] !== 'false';

  if (!empty($options['lockFile'])) {
    $lockFile = $options['lockFile'];

    // Prevent multiple CLI instances unless admin
    if (!$isAdmin && file_exists($lockFile)) {
      // If lock file exists, check if it's stale (older than 1 hour)
      $staleThreshold = 3600;
      // seconds
      $mtime = file_exists($lockFile) ? filemtime($lockFile) : 0;
      $age   = $mtime ? (time() - $mtime) : PHP_INT_MAX;
      if ($age > $staleThreshold) {
        // Remove stale lock
        _log_shared($hashFilename ?? 'CLI', "Found stale lock $lockFile (age={$age}s). Removing.");
        @unlink($lockFile);
      } else {
        $lockedMsg = date(DATE_RFC3339) . " another process still running ({$lockFile} is locked) ";
        _log_shared($hashFilename ?? 'CLI', $lockedMsg);
        exit($lockedMsg);
      }
    }

    // Create lock file to prevent concurrent execution and write PID/timestamp
    $lockInfo = json_encode(['pid' => getmypid(), 'ts' => date(DATE_RFC3339)]);
    write_file($lockFile, $lockInfo);

    // Schedule lock file removal after process completion
    Scheduler::register(function () use ($lockFile) {
      delete_path($lockFile);
    }, 'release-cli-lock');
  }

  // Determine file from CLI arguments
  $file = isset($options['f']) ? $options['f'] : (isset($options['file']) ? $options['file'] : null);

  // Generate hash filename based on file
  $baseHash = basename($file, '.txt');
  if ($baseHash == '.txt' || empty($baseHash)) {
    $hashFilename = "$currentScriptFilename/cli";
  } else {
    $hashFilename = $currentScriptFilename . '/' . $baseHash;
  }

  // Determine proxy input from CLI arguments
  $proxy = isset($options['p']) ? $options['p'] : (isset($options['proxy']) ? $options['proxy'] : null);

  // Load proxies if not provided
  if (!$file && !$proxy) {
    $proxy = load_proxies_for_mode($file, $proxy, 'http', $proxy_db);
  } elseif ($file) {
    $read = read_file($file);
    if ($read) {
      $proxy = $read;
    }
  }
}

// Ensure hash filename fallback
if (empty($hashFilename)) {
  $hashFilename = 'CLI';
}

// Define lock folder and file paths
$lockFolder   = unixPath(tmp() . '/runners/');
$lockFilePath = unixPath($lockFolder . $hashFilename . '.lock');
$lockFiles    = glob($lockFolder . "/$currentScriptFilename*.lock");

// Limit simultaneous processes to 2
if (count($lockFiles) > 2) {
  _log_shared($hashFilename ?? 'CLI', "Proxy checker process limit reached: More than 2 instances of '$currentScriptFilename' are running. Terminating process.");
  exit;
}

// Use FileLockHelper for robust locking (available via Composer autoload in shared.php)
use PhpProxyHunter\FileLockHelper;

$runAllowed = false;
$fileLock   = new FileLockHelper($lockFilePath);
if ($fileLock->lock()) {
  $runAllowed = true;

  if (isset($proxy) && !empty($proxy)) {
    truncateFile(get_log_file_shared($hashFilename ?? 'CLI'));
    check($proxy);
  }

  // Release via FileLockHelper
  $fileLock->unlock();
  if ($isAdmin) {
    _log_shared($hashFilename ?? 'CLI', "$lockFilePath Lock released");
  } else {
    _log_shared($hashFilename ?? 'CLI', 'Lock released');
  }
} else {
  _log_shared($hashFilename ?? 'CLI', 'Another process is still running');
  $runAllowed = false;
}

// Logging helpers provided by checker-runner.php

/**
 * Check if the proxy is working (HTTP only)
 * @param string $proxy proxy string or JSON array
 */
function check(string $proxy) {
  global $proxy_db, $hashFilename, $isAdmin, $isCli;

  // Extract proxies from string or array and shuffle
  $proxies = extractProxies($proxy, $proxy_db, true);
  shuffle($proxies);

  $count       = count($proxies);
  $logFilename = $hashFilename;
  _log_shared($hashFilename ?? 'CLI', trim('[' . ($isCli ? 'CLI' : 'WEB') . '][' . ($isAdmin ? 'admin' : 'user') . '] ' . substr($logFilename, 0, 6) . " Checking $count proxies..."));

  $startTime = microtime(true);
  $limitSecs = 120;
  // Closure to check execution time limit
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

    // Stop if time limit reached for non-admin
    if (!$isAdmin && $isExecutionTimeLimit()) {
      break;
    }

    // Check if proxy was recently checked (within 5 hours)
    $expired = $item->last_check ? isDateRFC3339OlderThanHours($item->last_check, 5) : true;

    // Skip recently checked active non-SSL proxies
    if ($item->status == 'active' && $item->https == 'false' && !$expired) {
      _log_shared($hashFilename ?? 'CLI', "[$no] Skipping proxy {$item->proxy}: Recently checked and non-SSL.");
      continue;
    }

    // Configure checker options
    $checkerOptions = new \PhpProxyHunter\Checker\CheckerOptions([
      'verbose'   => $isCli ? true : false,
      'timeout'   => 10,
      'protocols' => ['http', 'socks4', 'socks5'],
      'proxy'     => $item->proxy,
    ]);

    // Include authentication if available
    if (!empty($item->username)) {
      $checkerOptions->username = $item->username;
    }
    if (!empty($item->password)) {
      $checkerOptions->password = $item->password;
    }

    // Run HTTP-only proxy check
    $result = \PhpProxyHunter\Checker\ProxyCheckerHttpOnly::check($checkerOptions);

    // Prepare data for database update
    $data = ['last_check' => date(DATE_RFC3339)];
    if (!empty($result->latency)) {
      $data['latency'] = $result->latency;
    }

    // Update proxy status in database
    $retestStatus = null;
    if ($result->isWorking) {
      $data['status'] = 'active';
      $data['type']   = strtolower(implode('-', array_unique($result->workingTypes)));
    } else {
      // Re-test the proxy to confirm it's dead
      $retestResults  = reTestProxy($item->proxy, 5);
      $isAlive        = in_array(true, $retestResults, true);
      $data['status'] = $isAlive ? 'active' : 'dead';
      // Record a retest status for logging: alive, dead
      $retestStatus = $isAlive ? 'alive' : 'dead';
      // If alive on retest, update type and status accordingly
      if ($isAlive) {
        $workingTypes = [];
        foreach ($retestResults as $type => $worked) {
          if ($worked) {
            $workingTypes[] = $type;
          }
        }
        $data['type']         = strtolower(implode('-', array_unique($workingTypes)));
        $data['status']       = 'active';
        $result->isWorking    = true;
        $result->workingTypes = $workingTypes;
      }
    }

    $proxy_db->updateData($item->proxy, $data);

    // Build friendly per-proxy log line
    $statusSymbol = $result->isWorking ? '[OK]' : '[--]';
    $protocols    = !empty($result->workingTypes) ? implode(',', $result->workingTypes) : '';
    $latencyStr   = !empty($result->latency) ? (round($result->latency, 2) . 's') : '';
    // Determine retest logging value: either set above when retest ran or 'not-performed'
    if ($retestStatus === null) {
      $retestStatus = 'not-performed';
    }

    $lineParts = ["[$no]", $statusSymbol, $item->proxy];
    if ($protocols !== '') {
      $lineParts[] = 'protocols=' . $protocols;
    }
    if ($latencyStr !== '') {
      $lineParts[] = 'latency=' . $latencyStr;
    }

    // Include retest status in log (alive|dead|not-performed)
    $lineParts[] = 'retest=' . $retestStatus;

    _log_shared($hashFilename ?? 'CLI', implode(' ', $lineParts));
  }

  // Finished checking all proxies
  _log_shared($hashFilename ?? 'CLI', 'Done checking proxies.');
}

function reTestProxy(string $proxy, int $timeout = 5): array {
  $tests = [
    'http'   => CURLPROXY_HTTP,
    'socks4' => CURLPROXY_SOCKS4,
    'socks5' => CURLPROXY_SOCKS5,
  ];

  $results = [];

  foreach ($tests as $name => $curlType) {
    $ch = curl_init('http://httpbin.org/ip');
    // Browser-like curl options to make the probe more realistic
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    curl_setopt_array($ch, [
      CURLOPT_PROXY          => $proxy,
      CURLOPT_PROXYTYPE      => $curlType,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS      => 5,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT        => $timeout,
      CURLOPT_SSL_VERIFYPEER => false, // set to true if you want strict SSL checks
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_ENCODING       => '', // accept all supported encodings
      CURLOPT_USERAGENT      => $userAgent,
      CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Connection: keep-alive',
      ],
    ]);

    $response = curl_exec($ch);
    $err      = curl_errno($ch);
    curl_close($ch);

    $results[$name] = (!$err && !empty($response));
  }

  return $results;
}
