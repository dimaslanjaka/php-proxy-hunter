<?php

/**
 * Latency Proxy Checker
 *
 * Measures proxy latency by making continuous requests to http.cat or similar
 * forever endpoint and records average latency over multiple test iterations.
 * Updates database with working status, protocol types, and measured latency.
 *
 * Supports both web (REST API) and CLI execution modes.
 */

require_once __DIR__ . '/checker-runner.php';

use PhpProxyHunter\AnsiColors;
use PhpProxyHunter\Scheduler;
use PhpProxyHunter\Server;

global $isCli;

$isAdmin = $isCli;

if (!$isCli) {
  // Allow cross-origin requests
  PhpProxyHunter\Server::allowCors(true);

  // Set content type to plain text with UTF-8 encoding
  header('Content-Type: text/plain; charset=utf-8');

  // Parse request body or query string
  $req = parseQueryOrPostBody();

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
    $webServerLock  = tmp() . "/locks/$hashFilename.lock";
    $output_file    = tmp() . "/logs/$hashFilename.txt";
    $embedOutputUrl = getFullUrl($output_file);

    // Stop if lock file exists AND is not stale (less than 2 minutes old)
    if (file_exists($webServerLock)) {
      $mtime = filemtime($webServerLock);
      $age   = time() - $mtime;
      if ($age < 120) { // 2 minutes
        respond_json(['error' => true, 'message' => '[LATENCY] Another process is still running. Please try again later.', 'logFile' => $embedOutputUrl]);
      } else {
        // Lock is stale, remove it
        @unlink($webServerLock);
      }
    }

    // Get proxy string from request
    $proxy = $request['proxy'];

    // Save proxy to a temporary file
    $proxy_file = tmp() . "/proxies/$hashFilename.txt";
    write_file($proxy_file, $proxy);

    // Prepare output file and set permissions
    $file = __FILE__;
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
    $runner = tmp() . "/runners/$hashFilename" . ($isWin ? '.bat' : '.sh');
    write_file($runner, $cmd);

    // Execute the runner script in background
    runBashOrBatch($runner);

    respond_json(['error' => false, 'message' => '[LATENCY] Proxy latency check initiated.', 'logFile' => $embedOutputUrl]);
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
        exit;
      }
    }

    // Create lock file to prevent concurrent execution and write PID/timestamp
    $lockInfo = json_encode(['pid' => getmypid(), 'ts' => date(DATE_RFC3339)]);
    write_file($lockFile, $lockInfo);

    // Schedule lock file removal after process completion (silent cleanup, no logging)
    Scheduler::register(function () use ($lockFile) {
      @delete_path($lockFile);
    }, 'release-cli-lock');
  }

  // Determine file from CLI arguments
  $file = isset($options['f']) ? $options['f'] : (isset($options['file']) ? $options['file'] : null);

  // Generate hash filename from file
  $hashFilename = basename($file, '.txt');
  if ($hashFilename == '.txt' || empty($hashFilename)) {
    // Fallback hash filename
    $hashFilename = "$currentScriptFilename/cli";
  }

  // Determine proxy input from CLI arguments
  $proxy = isset($options['p']) ? $options['p'] : (isset($options['proxy']) ? $options['proxy'] : null);

  // Load proxies if not provided
  if (!$file && !$proxy) {
    $proxy = load_proxies_for_mode($file, $proxy, 'latency', $proxy_db);
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

// Use the lock file from web mode if provided via CLI args, otherwise create one
if (!empty($options['lockFile'])) {
  // Web mode provided the lock file path via --lockFile argument
  $lockFilePath = $options['lockFile'];
} else {
  // CLI mode without web request - create lock file path
  $lockFilePath = unixPath(tmp() . '/locks/' . $hashFilename . '.lock');
}

// Define lock folder and file paths
$lockFolder = unixPath(tmp() . '/locks/');
$lockFiles  = glob($lockFolder . "/$currentScriptFilename*.lock");

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
 * Check proxy latency by making continuous requests via buildCurl
 * @param string $proxy proxy string or JSON array
 */
function check($proxy) {
  global $proxy_db, $hashFilename, $isAdmin, $isCli, $userId;

  // Extract proxies from string or array and shuffle
  $proxies = extractProxies($proxy, $proxy_db, true);
  shuffle($proxies);

  $count   = count($proxies);
  $logHash = substr($userId, 0, 6);
  _log_shared($hashFilename ?? 'CLI', trim('[' . ($isCli ? 'CLI' : 'WEB') . '][' . ($isAdmin ? 'admin' : 'user') . '] ' . $logHash . " Measuring latency for $count proxies..."));

  $startTime = microtime(true);
  $limitSecs = 120;
  // Closure to check execution time limit
  $isExecutionTimeLimit = function () use ($startTime, $limitSecs, $hashFilename) {
    $elapsedTime = microtime(true) - $startTime;
    if ($elapsedTime > $limitSecs) {
      _log_shared($hashFilename ?? 'CLI', "Proxy latency checker execution limit reached {$limitSecs}s.");
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

    // Measure latency using buildCurl with multiple iterations
    $latencies = measureProxyLatency($item->proxy, $item->username ?? null, $item->password ?? null);

    // Prepare data for database update - only latency
    $data = [];

    // Store only latency, no other fields
    if (!empty($latencies['average'])) {
      $data['latency'] = $latencies['average'];
    }

    // Update only if there's latency data
    if (!empty($data)) {
      $proxy_db->updateData($item->proxy, $data);
    }

    // Determine if proxy is working for logging purposes only
    $isWorking = !empty($latencies['success_count']) && $latencies['success_count'] > 0;

    // Build friendly per-proxy log line
    $statusSymbol = $isWorking ? '[' . AnsiColors::colorize(['green', 'bold'], 'OK') . ']' : '[' . AnsiColors::colorize(['red', 'bold'], 'FAIL') . ']';
    $proxyPart    = AnsiColors::colorize(['cyan'], $item->proxy);

    $lineParts = ["[$no]", $statusSymbol, $proxyPart];

    // Add latency information
    if (!empty($latencies['average'])) {
      $avgLatency  = round($latencies['average'], 3);
      $minLatency  = round($latencies['min'] ?? 0, 3);
      $maxLatency  = round($latencies['max'] ?? 0, 3);
      $lineParts[] = "latency={$avgLatency}s (min={$minLatency}s, max={$maxLatency}s)";
    }

    // Add iteration count
    $successCount = $latencies['success_count'] ?? 0;
    $totalCount   = $latencies['total_count']   ?? 0;
    $lineParts[]  = "iterations={$successCount}/{$totalCount}";

    _log_shared($hashFilename ?? 'CLI', implode(' ', $lineParts));
  }

  // Finished checking all proxies
  _log_shared($hashFilename ?? 'CLI', 'Done measuring proxy latencies.');
}

/**
 * Measure proxy latency by making multiple requests through the proxy
 * Tests all supported protocols: http, socks4, socks5, socks4a, socks5h
 * Uses buildCurl to test connectivity and extracts latency from curl_getinfo()
 *
 * @param string $proxy Proxy address (e.g., "192.168.1.1:8080")
 * @param string|null $username Optional proxy authentication username
 * @param string|null $password Optional proxy authentication password
 * @return array Latency statistics with keys: average, min, max, success_count, total_count
 */
function measureProxyLatency($proxy, $username = null, $password = null) {
  $iterations = 5;
  // Number of test iterations per protocol
  $latencies = [];
  $endpoint  = 'http://httpforever.com/';
  // Forever endpoint that always responds

  // Protocols to test
  $protocols = ['http', 'socks4', 'socks5', 'socks4a', 'socks5h'];

  foreach ($protocols as $protocol) {
    for ($i = 0; $i < $iterations; $i++) {
      // Build cURL handle with proxy via buildCurl
      $ch = \buildCurl(
        $proxy,           // proxy address
        $protocol,        // proxy type: http, socks4, socks5, socks4a, socks5h
        $endpoint,        // endpoint to test
        [],               // headers (empty - will use defaults)
        $username,        // username
        $password,        // password
        'GET',            // HTTP method
        null,             // post data
        0                 // SSL version
      );

      if (!$ch) {
        continue;
      }

      // Suppress output
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

      // Execute the request
      $response = @curl_exec($ch);
      $info     = curl_getinfo($ch);
      $httpCode = isset($info['http_code']) ? $info['http_code'] : 0;

      // Check for successful response and extract built-in latency from curl_getinfo()
      if ($response !== false && $httpCode >= 200 && $httpCode < 400) {
        // Use curl's built-in total_time (in seconds) as latency
        if (!empty($info['total_time'])) {
          $latencies[] = $info['total_time'];
        }
      }

      curl_close($ch);
    }
  }

  // Calculate statistics
  $successCount = count($latencies);
  $totalCount   = count($protocols) * $iterations;
  $average      = $successCount > 0 ? array_sum($latencies) / $successCount : 0;
  $min          = $successCount > 0 ? min($latencies) : 0;
  $max          = $successCount > 0 ? max($latencies) : 0;

  return [
    'average'       => $average,
    'min'           => $min,
    'max'           => $max,
    'success_count' => $successCount,
    'total_count'   => $totalCount,
  ];
}
