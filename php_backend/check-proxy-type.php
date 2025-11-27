<?php

/**
 * Proxy Type Detector
 *
 * Detects proxy protocol types (HTTP, SOCKS4, SOCKS5, etc) and validates
 * port connectivity. Updates database with detected type and status.
 *
 * Supports both web (REST API) and CLI execution modes.
 */

require_once __DIR__ . '/checker-runner.php';

use PhpProxyHunter\Checker\ProxyTypeDetector;
use PhpProxyHunter\Scheduler;
use PhpProxyHunter\Server;

global $isCli;

$isAdmin = $isCli;

if (!$isCli) {
  PhpProxyHunter\Server::allowCors(true);

  // Set content type to plain text with UTF-8 encoding
  header('Content-Type: text/plain; charset=utf-8');

  // Parse request body or query parameters
  $req = parseQueryOrPostBody();

  // Check if the user has admin privileges
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

$userId                = getUserId();
$request               = parseQueryOrPostBody();
$currentScriptFilename = basename(__FILE__, '.php');
$full_url              = Server::getCurrentUrl(true);

// Set maximum execution time to 300 seconds
ini_set('max_execution_time', 300);
if (function_exists('set_time_limit')) {
  // Reset the timer and set the maximum execution time
  call_user_func('set_time_limit', 300);
}

if (!$isCli) {
  if (isset($request['proxy'])) {
    $hashFilename   = "$currentScriptFilename/$userId";
    $output_file    = tmp() . "/logs/$hashFilename.txt";
    $embedOutputUrl = getFullUrl($output_file);

    // Define web server lock file path
    $webServerLock = tmp() . "/locks/$hashFilename.lock";

    // Stop if lock file exists AND is not stale (less than 2 minutes old)
    if (file_exists($webServerLock)) {
      $mtime = filemtime($webServerLock);
      $age   = time() - $mtime;
      if ($age < 120) { // 2 minutes
        respond_json(['error' => true, 'message' => '[TYPE] Another process is still running. Please try again later.', 'logFile' => $embedOutputUrl]);
      } else {
        // Lock is stale, remove it
        @unlink($webServerLock);
      }
    }

    // Get proxy from the request
    $proxy = $request['proxy'];

    // Define path for proxy file
    $proxy_file = tmp() . "/proxies/$hashFilename.txt";
    // Save proxy details to the file
    write_file($proxy_file, $proxy);

    // Prepare for long-running background process
    $file = __FILE__;
    // Set file permissions
    setMultiPermissions([$file, $output_file], true);

    // Check if the system is Windows
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    // Build the PHP execution command
    $cmd = getPhpExecutable(true) . ' ' . escapeshellarg($file);
    // Append user ID argument
    $cmd .= ' --userId=' . escapeshellarg($userId);
    // Append proxy file argument
    $cmd .= ' --file=' . escapeshellarg($proxy_file);
    // Append admin flag
    $cmd .= ' --admin=' . escapeshellarg($isAdmin ? 'true' : 'false');
    // Append lock file argument
    $cmd .= ' --lockFile=' . escapeshellarg(unixPath($webServerLock));
    // Trim whitespace from the command
    $cmd = trim($cmd);

    // Redirect output and errors to a log file
    $cmd = sprintf('%s > %s 2>&1', $cmd, escapeshellarg($output_file));

    // Create a runner script for the command
    $runner = tmp() . "/runners/$hashFilename" . ($isWin ? '.bat' : '.sh');
    // Save the command into the runner script
    write_file($runner, $cmd);

    // Execute the runner script in the background
    runBashOrBatch($runner);

    respond_json(['error' => false, 'message' => '[TYPE] Proxy type detection initiated.', 'logFile' => $embedOutputUrl]);
  } else {
    // Show usage instructions for direct web access
    echo 'Usage:' . PHP_EOL;
    respond_text("\tcurl -X POST $full_url -d \"proxy=72.10.160.171:24049\"");
    exit;
  }
  exit;
}

// Parse command line options (short: -f, -p; long: --file, --proxy, --admin)
$options = getopt('f::p::', ['file::', 'proxy::', 'admin::', 'lockFile::']);

// Set admin flag if provided
$isAdmin = !empty($options['admin']) && $options['admin'] !== 'false';

if (!empty($options['lockFile'])) {
  $lockFile = $options['lockFile'];

  // Prevent multiple instances unless admin
  if (!$isAdmin && file_exists($lockFile)) {
    // Check for stale lock older than 1 hour
    $staleThreshold = 3600;
    // seconds
    $mtime = file_exists($lockFile) ? filemtime($lockFile) : 0;
    $age   = $mtime ? (time() - $mtime) : PHP_INT_MAX;
    if ($age > $staleThreshold) {
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

  // Schedule lock file removal after completion (silent cleanup, no logging)
  Scheduler::register(function () use ($lockFile) {
    @delete_path($lockFile);
  }, 'release-cli-lock');
}

// Determine the file path from command line
$file = isset($options['f']) ? $options['f'] : (isset($options['file']) ? $options['file'] : null);

// Generate hash filename from file
$hashFilename = basename($file, '.txt');
if ($hashFilename == '.txt' || empty($hashFilename)) {
  // Fallback hash filename
  $hashFilename = "$currentScriptFilename/cli";
}

// Determine proxy source from command line
$proxy = isset($options['p']) ? $options['p'] : (isset($options['proxy']) ? $options['proxy'] : null);

// Load proxies if none provided
if (!$file && !$proxy) {
  $proxy = load_proxies_for_mode($file, $proxy, 'type', $proxy_db);
} elseif ($file) {
  // Read proxies from file
  $read = read_file($file);
  if ($read) {
    $proxy = $read;
  }
}

// Ensure fallback hash filename
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

$lockFolder = unixPath(tmp() . '/locks/');
$lockFiles  = glob($lockFolder . "/$currentScriptFilename*.lock");
// Exit if too many lock files exist
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
    // Clear shared log and run proxy checks
    truncateFile(get_log_file_shared($hashFilename ?? 'CLI'));
    check($proxy);
  }

  // Release lock via helper
  $fileLock->unlock();
  if ($isAdmin) {
    _log_shared($hashFilename ?? 'CLI', "$lockFilePath Lock released");
  } else {
    _log_shared($hashFilename ?? 'CLI', 'Lock released');
  }
} else {
  // Skip execution if another process is running
  _log_shared($hashFilename ?? 'CLI', 'Another process is still running');
  $runAllowed = false;
}

// FileLockHelper will clean up the lock file on unlock/destruct

// Logging and helper functions are provided by checker-runner.php

/**
 * Check proxy types for given proxies
 * @param string $proxy proxy string or JSON array
 */
function check($proxy) {
  global $proxy_db, $hashFilename, $isAdmin, $isCli;

  // Extract proxies from string or array and shuffle
  $proxies = extractProxies($proxy, $proxy_db, true);
  shuffle($proxies);

  $count       = count($proxies);
  $logFilename = $hashFilename;
  _log_shared($hashFilename ?? 'CLI', trim('[' . ($isCli ? 'CLI' : 'WEB') . '][' . ($isAdmin ? 'admin' : 'user') . '] ' . substr($logFilename, 0, 6) . " Detecting $count proxy types..."));

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

    if (!$expired) {
      _log_shared($hashFilename ?? 'CLI', "[$no] [--] {$item->proxy} Skipped recently checked proxy");
      continue;
    }

    // Detect proxy type
    $detection = ProxyTypeDetector::detect($item->proxy);
    $type      = $detection['result'];
    $portOpen  = $detection['port-open'];
    $isValid   = $detection['proxy-valid'];

    if (!$isValid) {
      $proxy_db->remove($item->proxy);
      _log_shared($hashFilename ?? 'CLI', "[$no] [--] {$item->proxy} Removed invalid proxy");
      continue;
    } elseif ($portOpen === false) {
      _log_shared($hashFilename ?? 'CLI', "[$no] [--] {$item->proxy} Port closed");
      $proxy_db->updateData($item->proxy, [
        'status'     => 'port-closed',
        'last_check' => date(DATE_RFC3339),
      ]);
      continue;
    } elseif ($type === 'unknown') {
      if ($portOpen === true && $isValid === true) {
        // Proxy is valid and port is open but type unknown
        if (in_array($item->status, ['inactive', 'dead', 'port-closed']) || empty($item->status)) {
          $proxy_db->updateData($item->proxy, [
            'status'     => 'untested',
            'last_check' => date(DATE_RFC3339),
          ]);
          _log_shared($hashFilename ?? 'CLI', "[$no] [--] {$item->proxy} type=unknown status=untested");
        } else {
          _log_shared($hashFilename ?? 'CLI', "[$no] [--] {$item->proxy} type=unknown");
        }
      } else {
        // Should not reach here since portOpen and isValid are already checked
        _log_shared($hashFilename ?? 'CLI', "[$no] [--] {$item->proxy} Unexpected state type=unknown");
      }
    } else {
      // Proxy is valid and port is open and type detected
      // Mark as untested when current status is inactive or dead or port-closed or empty
      if (in_array($item->status, ['inactive', 'dead', 'port-closed']) || empty($item->status)) {
        $proxy_db->updateData($item->proxy, [
          'status' => 'untested',
          'type'   => $type,
        ]);
        _log_shared($hashFilename ?? 'CLI', "[$no] [OK] {$item->proxy} type=$type status=untested");
      } else {
        _log_shared($hashFilename ?? 'CLI', "[$no] [OK] {$item->proxy} type=$type");
      }
    }
  }

  // Finished checking all proxies
  _log_shared($hashFilename ?? 'CLI', 'Done detecting proxy types.');
}
