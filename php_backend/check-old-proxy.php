<?php

require_once __DIR__ . '/checker-runner.php';

use PhpProxyHunter\AnsiColors;
use PhpProxyHunter\CoreDB;
use PhpProxyHunter\Scheduler;
use PhpProxyHunter\Server;

global $isAdmin, $dbFile, $dbHost, $dbName, $dbUser, $dbPass, $dbType;

// Set maximum execution time to 300 seconds
ini_set('max_execution_time', 300);
if (function_exists('set_time_limit')) {
  call_user_func('set_time_limit', 300);
}

$isCli           = is_cli();
$userId          = getUserId();
$currentFileName = basename(__FILE__, '.php');
$isAdmin         = $isCli;
$hashFilename    = "$currentFileName/$userId";

if (!$isCli) {
  Server::allowCors(true);

  // Generate hash filename and paths
  $output_file    = tmp() . "/logs/$hashFilename.txt";
  $embedOutputUrl = getFullUrl($output_file);
  $webServerLock  = tmp() . "/locks/$hashFilename.lock";

  // Check if another process is running
  if (file_exists($webServerLock)) {
    $mtime = filemtime($webServerLock);
    $age   = time() - $mtime;
    if ($age < 120) { // 2 minutes
      respond_json(['error' => true, 'message' => '[OLD-PROXY] Another process is still running. Please try again later.', 'logFile' => $embedOutputUrl]);
    } else {
      // Lock is stale, remove it
      @unlink($webServerLock);
    }
  }

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

  respond_json(['error' => false, 'message' => '[OLD-PROXY] Proxy age check initiated.', 'logFile' => $embedOutputUrl]);
}

// Parse command line options
$options = getopt('f::p::', ['file::', 'proxy::', 'admin::', 'lockFile::']);

// Set admin flag if provided
$isAdmin = !empty($options['admin']) && $options['admin'] !== 'false';

if (!empty($options['lockFile'])) {
  $lockFile = $options['lockFile'];

  // Prevent multiple CLI instances unless admin
  if (!$isAdmin && file_exists($lockFile)) {
    // Check if lock is stale (older than 1 hour)
    $staleThreshold = 3600;
    $mtime          = file_exists($lockFile) ? filemtime($lockFile) : 0;
    $age            = $mtime ? (time() - $mtime) : PHP_INT_MAX;
    if ($age > $staleThreshold) {
      _log_shared($hashFilename, "Found stale lock $lockFile (age={$age}s). Removing.");
      @unlink($lockFile);
    } else {
      $lockedMsg = date(DATE_RFC3339) . " another process still running ({$lockFile} is locked) ";
      _log_shared($hashFilename, $lockedMsg);
      exit;
    }
  }

  // Create lock file to prevent concurrent execution
  $lockInfo = json_encode(['pid' => getmypid(), 'ts' => date(DATE_RFC3339)]);
  write_file($lockFile, $lockInfo);

  // Schedule lock file removal after completion
  Scheduler::register(function () use ($lockFile) {
    @delete_path($lockFile);
  }, 'release-cli-lock');
}

$core_db  = new CoreDB($dbFile, $dbHost, $dbName, $dbUser, $dbPass, false, $dbType);
$proxy_db = $core_db->proxy_db;

$startTime = microtime(true);
$limitSecs = 120;
// Closure to check execution time limit
$isExecutionTimeLimit = function () use ($startTime, $limitSecs, $hashFilename) {
  $elapsedTime = microtime(true) - $startTime;
  if ($elapsedTime > $limitSecs) {
    _log_shared($hashFilename, AnsiColors::colorize(['red', 'bold'], "Proxy checker execution limit reached {$limitSecs}s."));
    return true;
  }
  return false;
};

// Log the start of the proxy age check
$logHash = substr($userId, 0, 6);
_log_shared($hashFilename, trim('[' . ($isCli ? 'CLI' : 'WEB') . '][' . ($isAdmin ? 'admin' : 'user') . '] ' . $logHash . ' Starting old proxy age check...'));

$page  = 0;
$limit = 100;
$items = [];
while (true) {
  // Check execution time limit
  if (!$isAdmin && $isExecutionTimeLimit()) {
    break;
  }

  $items = $proxy_db->getAllProxies(1000, true, $page, $limit);
  if (empty($items)) {
    break;
  }

  // Separate items into active and non-active groups for prioritization
  $activeItems   = [];
  $inactiveItems = [];
  foreach ($items as $item) {
    $lastChecked = strtotime($item['last_check']);
    // Filter out items checked recently (age < 1 day)
    if ($lastChecked !== false) {
      $ageInDays = round((time() - $lastChecked) / 86400);
      if ($ageInDays < 1) {
        _log_shared($hashFilename, '  -> Skipped (checked recently)');
        continue;
      }
    }

    // Categorize by status
    if ($item['status'] === 'active') {
      $activeItems[] = $item;
    } else {
      $inactiveItems[] = $item;
    }
  }

  // Merge active items first, then inactive items
  $prioritizedItems = array_merge($activeItems, $inactiveItems);

  // Release memory at end of loop
  unset($items, $activeItems, $inactiveItems);
  gc_collect_cycles();

  foreach ($prioritizedItems as $item) {
    // Check execution time limit per item
    if (!$isAdmin && $isExecutionTimeLimit()) {
      break 2;
    }

    $proxyPart = AnsiColors::colorize(['cyan'], $item['proxy']);
    $daysPart  = AnsiColors::colorize(['magenta', 'bold'], (string)$ageInDays);
    $message   = 'Checking ' . $proxyPart . ' last checked ' . $daysPart . ' days ago';
    _log_shared($hashFilename, $message);

    $isPortOpen = isPortOpen($item['proxy'], 30);
    if (!$isPortOpen) {
      $proxyPart = AnsiColors::colorize(['cyan'], $item['proxy']);
      $message   = '  -> ' . AnsiColors::colorize(['red', 'bold'], 'Port is closed');
      _log_shared($hashFilename, $message);
      $proxy_db->updateStatus($item['proxy'], 'port-closed');
      continue;
    }

    $checkerOptions = new \PhpProxyHunter\Checker\CheckerOptions([
      'verbose'   => true,
      'timeout'   => 10,
      'protocols' => ['http', 'socks4', 'socks5', 'socks4a', 'socks5h'],
      'proxy'     => $item['proxy'],
    ]);

    // Include authentication if available
    if (!empty($item['username']) && !empty($item['password'])) {
      $checkerOptions->username = $item['username'];
      $checkerOptions->password = $item['password'];
    }

    /** @var \PhpProxyHunter\Checker\CheckerResult $httpOnly */
    $httpOnly = \PhpProxyHunter\Checker\ProxyCheckerHttpOnly::check($checkerOptions);
    /** @var \PhpProxyHunter\Checker\CheckerResult $httpsOnly */
    $httpsOnly = \PhpProxyHunter\Checker\ProxyCheckerHttpsOnly::check($checkerOptions);

    // Merge working protocols from both HTTP and HTTPS checks
    $mergedWorkingTypes = array_unique(array_merge(
      $httpOnly->isWorking ? $httpOnly->workingTypes : [],
      $httpsOnly->isWorking ? $httpsOnly->workingTypes : []
    ));

    if ($httpOnly->isWorking) {
      $data = [
        'https'      => 'false',
        'status'     => 'active',
        'last_check' => date(DATE_RFC3339),
        'type'       => strtolower(implode('-', $mergedWorkingTypes)),
      ];
      // Record latency when available from the HTTP check
      if (!empty($httpOnly->latency)) {
        $data['latency'] = $httpOnly->latency;
      }
      $proxy_db->updateData($item['proxy'], $data);
      $protocols = !empty($mergedWorkingTypes) ? implode(',', $mergedWorkingTypes) : '';
      $message   = '  -> ' . AnsiColors::colorize(['green', 'bold'], 'Proxy is active (HTTP)');
      if ($protocols !== '') {
        $message .= ' protocols=' . $protocols;
      }
      _log_shared($hashFilename, $message);
    }

    if ($httpsOnly->isWorking) {
      $data = [
        'https'      => 'true',
        'status'     => 'active',
        'last_check' => date(DATE_RFC3339),
        'type'       => strtolower(implode('-', $mergedWorkingTypes)),
      ];
      // Record latency when available from the HTTPS check
      if (!empty($httpsOnly->latency)) {
        $data['latency'] = $httpsOnly->latency;
      }
      $proxy_db->updateData($item['proxy'], $data);
      $protocols = !empty($mergedWorkingTypes) ? implode(',', $mergedWorkingTypes) : '';
      $message   = '  -> ' . AnsiColors::colorize(['green', 'bold'], 'Proxy is active (HTTPS)');
      if ($protocols !== '') {
        $message .= ' protocols=' . $protocols;
      }
      _log_shared($hashFilename, $message);
    }

    if (!$httpOnly->isWorking && !$httpsOnly->isWorking) {
      // Re-test the proxy to confirm it's dead
      $retestResults = reTestProxy(new \PhpProxyHunter\Proxy($item['proxy'], $item['username'], $item['password']));
      $isAlive       = in_array(true, $retestResults, true);
      $retestStatus  = $isAlive ? AnsiColors::colorize(['green', 'bold'], 'active') : AnsiColors::colorize(['red', 'bold'], 'dead');
      if ($isAlive) {
        /// Port is actually open, mark as untested for later detection
        $proxy_db->updateStatus($item['proxy'], 'untested');
        $message = '  -> ' . AnsiColors::colorize(['yellow', 'bold'], 'Proxy marked as untested') . ' retest=' . $retestStatus;
        _log_shared($hashFilename, $message);
      } else {
        $proxy_db->updateStatus($item['proxy'], 'dead');
        $message = '  -> ' . AnsiColors::colorize(['red', 'bold'], 'Proxy is dead') . ' retest=' . $retestStatus;
        _log_shared($hashFilename, $message);
      }
    }
  }

  // Release memory at end of loop
  unset($prioritizedItems, $item);
  gc_collect_cycles();

  $page++;
}

_log_shared($hashFilename, 'Done checking old proxies.');
