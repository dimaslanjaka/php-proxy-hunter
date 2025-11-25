<?php

require_once __DIR__ . '/checker-runner.php';

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
    // Generate a unique hash filename based on the script and user ID
    // do not use $file here (undefined); use current script name + user id
    $hashFilename   = "$currentScriptFilename/$userId";
    $output_file    = tmp() . "/logs/$hashFilename.txt";
    $embedOutputUrl = getFullUrl($output_file);

    // Define web server lock file path
    $webServerLock = tmp() . "/locks/$hashFilename.lock";

    // Stop if lock file exists (another process running)
    if (file_exists($webServerLock)) {
      respond_json(['error' => true, 'message' => '[HTTPS] Another process is still running. Please try again later.', 'logFile' => $embedOutputUrl]);
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

    respond_json(['error' => false, 'message' => '[HTTPS] Proxy check initiated.', 'logFile' => $embedOutputUrl]);
  } else {
    // Show usage instructions for direct web access
    echo 'Usage:' . PHP_EOL;
    respond_text("\tcurl -X POST $full_url -d \"proxy=72.10.160.171:24049\"");
    exit;
  }
} else {
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

    // Schedule lock file removal after completion
    Scheduler::register(function () use ($lockFile) {
      delete_path($lockFile);
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
    $proxy = load_proxies_for_mode($file, $proxy, 'https', $proxy_db);
  } elseif ($file) {
    // Read proxies from file
    $read = read_file($file);
    if ($read) {
      $proxy = $read;
    }
  }
}

// Ensure fallback hash filename
if (empty($hashFilename)) {
  $hashFilename = 'CLI';
}

$lockFolder   = unixPath(tmp() . '/runners/');
$lockFilePath = unixPath($lockFolder . $hashFilename . '.lock');
$lockFiles    = glob($lockFolder . "/$currentScriptFilename*.lock");
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
 * Check if the proxy is working
 * @param string $proxy proxy string
 */
function check(string $proxy) {
  global $proxy_db, $hashFilename, $isAdmin, $isCli;
  // Extract proxies and shuffle order
  $proxies = extractProxies($proxy, $proxy_db, true);
  shuffle($proxies);

  $count       = count($proxies);
  $logFilename = $hashFilename ?? 'CLI';
  _log_shared($hashFilename ?? 'CLI', trim('[' . ($isCli ? 'CLI' : 'WEB') . '][' . ($isAdmin ? 'admin' : 'user') . '] ' . substr($logFilename, 0, 6) . " Checking $count proxies..."));

  // Record start time for execution limit check
  $startTime            = microtime(true);
  $limitSecs            = 120;
  $isExecutionTimeLimit = function () use ($startTime, $limitSecs) {
    // Return true if script exceeds time limit
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

    // Stop if execution time limit reached for non-admin
    if (!$isAdmin && $isExecutionTimeLimit()) {
      break;
    }

    // Check if proxy last checked more than 5 hours ago
    $expired = $item->last_check ? isDateRFC3339OlderThanHours($item->last_check, 5) : true;

    // Skip proxy if already active and SSL enabled recently
    if ($item->https == 'true' && $item->status == 'active' && !$expired) {
      _log_shared($hashFilename ?? 'CLI', "[$no] Skipping proxy {$item->proxy}: Already supports SSL and is active.");
      continue;
    } elseif ($item->last_check) {
      // Skip dead or non-SSL proxies if recently checked
      if ($item->status == 'dead') {
        if ($item->last_check && !$expired) {
          _log_shared($hashFilename ?? 'CLI', "[$no] Skipping proxy {$item->proxy}: Marked as dead, but was recently checked at {$item->last_check}.");
          continue;
        }
      } elseif ($item->https == 'false' && $item->status != 'untested') {
        if ($item->last_check && !$expired) {
          _log_shared($hashFilename ?? 'CLI', "[$no] Skipping proxy {$item->proxy}: Does not support SSL, but was recently checked at {$item->last_check}.");
          continue;
        }
      }
    }

    // Initialize data containers
    $ssl_protocols = [];
    $protocols     = ['http', 'socks4', 'socks5'];
    $latencies     = [];

    // Test each protocol
    foreach ($protocols as $protocol) {
      $curl = buildCurl($item->proxy, $protocol, 'https://support.mozilla.org/en-US/', [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
      ]);
      $result = curl_exec($curl);
      $msg    = "[$no] $protocol://{$item->proxy} ";
      $reason = '';

      if ($result) {
        // Get HTTP info
        $info = curl_getinfo($curl);
        curl_close($curl);
        if ($info['http_code'] == 200) {
          // Record total time
          $msg .= round($info['total_time'], 2) . 's ';
          $latencies[] = round($info['total_time'] * 1000, 2);

          // Check if SSL is dead
          if (checkRawHeadersKeywords($result)) {
            $reason = 'SSL dead (Azenv/suspicious headers detected)';
            $msg .= $reason . '. ';
          } else {
            // Parse page title
            preg_match("/<title>(.*?)<\/title>/is", $result, $matches);
            if (!empty($matches)) {
              $msg .= 'Title: ' . $matches[1];
              if (strtolower($matches[1]) == strtolower('Mozilla Support')) {
                $msg .= ' (VALID) ';
                $ssl_protocols[] = $protocol;
              } else {
                $reason = 'SSL dead (invalid page title)';
                $msg .= ' (INVALID) ';
              }
            } else {
              $reason = 'SSL dead (no page title found)';
              $msg .= 'Title: N/A ';
            }
          }
        } else {
          $reason = "SSL dead (HTTP {$info['http_code']} returned)";
          $msg .= $reason . ' ';
        }
      } else {
        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);
        $reason    = "SSL dead (CURL error: $curlErrno - $curlError)";
        $msg .= $reason . ' ';
        curl_close($curl);
      }

      // Log per-protocol result
      _log_shared($hashFilename ?? 'CLI', trim($msg));
    }

    // Prepare base data for database update
    $data = ['https' => !empty($ssl_protocols) ? 'true' : 'false', 'last_check' => date(DATE_RFC3339)];

    // Add SSL protocols and latency info if available
    if (!empty($ssl_protocols)) {
      $data['type']   = join('-', $ssl_protocols);
      $data['status'] = 'active';
      if (!empty($latencies)) {
        $data['latency'] = max($latencies);
      }
    }

    // Update proxy info in database
    $proxy_db->updateData($item->proxy, $data);

    // Prepare friendly log line for summary
    $statusSymbol = (!empty($ssl_protocols) && count($ssl_protocols) > 0) ? '[OK]' : '[--]';
    $protocolsStr = !empty($ssl_protocols) ? implode(',', $ssl_protocols) : '';
    $latencyStr   = '';
    if (!empty($latencies)) {
      $lat        = isset($data['latency']) ? $data['latency'] : max($latencies);
      $latencyStr = round($lat / 1000, 2) . 's';
    }

    // Determine reason if proxy is dead
    $reasonStr = '';
    if (empty($ssl_protocols)) {
      if (!empty($latencies)) {
        $reasonStr = 'reason=no-valid-ssl-protocols';
      } else {
        $reasonStr = 'reason=connection-failed';
      }
    }

    // Build final log line
    $lineParts = ["[$no]", $statusSymbol, $item->proxy];
    if ($protocolsStr !== '') {
      $lineParts[] = 'protocols=' . $protocolsStr;
    }
    if ($latencyStr !== '') {
      $lineParts[] = 'latency=' . $latencyStr;
    }
    if ($reasonStr !== '') {
      $lineParts[] = $reasonStr;
    }

    // Log final summary for proxy
    _log_shared($hashFilename ?? 'CLI', implode(' ', $lineParts));
  }

  // Done processing all proxies
  _log_shared($hashFilename ?? 'CLI', 'Done checking proxies.');
}
