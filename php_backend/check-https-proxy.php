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
  // Resets the timer and sets the max execution time to 300 seconds
  call_user_func('set_time_limit', 300);
}

if (!$isCli) {
  if (isset($request['proxy'])) {
    // Generate a unique hash filename based on the script and user ID
    $hashFilename = "$currentScriptFilename/$userId";

    // Web server setup and process lock
    $webServerLock = tmp() . "/runners/$hashFilename.proc";
    // Define a lock file path

    // Handle proxy configuration
    $proxy = $request['proxy'];
    // Extract proxy information from the request

    $proxy_file = tmp() . "/proxies/$hashFilename.txt";
    // Define the path for the proxy file
    write_file($proxy_file, $proxy);
    // Save the proxy details to the file

    // Prepare for a long-running background process
    $file = __FILE__;
    // Get the current script's filename
    $output_file = tmp() . "/logs/$hashFilename.txt";
    // Define the path for the output log file
    setMultiPermissions([$file, $output_file], true);
    // Set appropriate permissions for the script and log file

    // Determine if the system is Windows
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    // Build the base command to run the PHP script
    $cmd = 'php ' . escapeshellarg($file);
    // Add the user ID as a CLI argument
    $cmd .= ' --userId=' . escapeshellarg($userId);
    // Add the proxy file path as a CLI argument
    $cmd .= ' --file=' . escapeshellarg($proxy_file);
    // Add the admin flag as a CLI argument (true/false)
    $cmd .= ' --admin=' . escapeshellarg($isAdmin ? 'true' : 'false');
    // Add the --lockFile argument with the escaped web server lock file path
    $cmd .= ' --lockFile=' . escapeshellarg(unixPath($webServerLock));
    // Trim any leading/trailing whitespace from the full command
    $cmd = trim($cmd);

    echo $cmd . "\n\n";

    // Redirect output and errors to a log file
    $cmd = sprintf('%s > %s 2>&1', $cmd, escapeshellarg($output_file));

    // Create a runner script for the command
    $runner = tmp() . "/runners/$hashFilename" . ($isWin ? '.bat' : '');
    // Use .bat for Windows, no extension for others
    write_file($runner, $cmd);
    // Write the command to the runner script

    // Execute the runner script in the background
    runBashOrBatch($runner);
    exit;
  // Exit the current script
  } else {
    // Direct access
    echo 'Usage:' . PHP_EOL;
    respond_text("\tcurl -X POST $full_url -d \"proxy=72.10.160.171:24049\"");
    exit;
  }
} else {
  // Parse command line options (short: -f, -p; long: --file, --proxy, --admin), all optional
  $options = getopt('f::p::', ['file::', 'proxy::', 'admin::', 'lockFile::']);

  // Set admin flag if --admin is provided and is not 'false'
  $isAdmin = !empty($options['admin']) && $options['admin'] !== 'false';

  if (!empty($options['lockFile'])) {
    $lockFile = $options['lockFile'];

    // If the lock file exists, exit with an error message
    if (!$isAdmin && file_exists($lockFile)) {
      $lockedMsg = date(DATE_RFC3339) . " another process still running ({$lockFile} is locked) ";
      _log_shared($hashFilename ?? 'CLI', $lockedMsg);
      exit($lockedMsg);
    }

    // Create the lock file to prevent concurrent execution
    write_file($lockFile, '');

    // Always schedule the removal of the lock file after the process completes
    Scheduler::register(function () use ($lockFile) {
      delete_path($lockFile);
    // Remove the lock file
    }, 'release-cli-lock');
  }

  // Determine the file path from either -f or --file
  $file = isset($options['f']) ? $options['f'] : (isset($options['file']) ? $options['file'] : null);

  // Create a filename hash identifier (used for output/processing)
  $hashFilename = basename($file, '.txt');

  // Fallback filename if no valid file name is provided
  if ($hashFilename == '.txt' || empty($hashFilename)) {
    $hashFilename = "$currentScriptFilename/cli.txt";
  }

  // Determine the proxy source from either -p or --proxy
  $proxy = isset($options['p']) ? $options['p'] : (isset($options['proxy']) ? $options['proxy'] : null);

  // If no proxy file or string is provided, fallback to loading from the database
  if (!$file && !$proxy) {
    $proxy = load_proxies_for_mode($file, $proxy, 'https', $proxy_db);
  } elseif ($file) {
    // If a file is provided, read the content as the proxy list
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
// Check if the number of lock files exceeds the limit
if (count($lockFiles) > 2) {
  _log_shared($hashFilename ?? 'CLI', "Proxy checker process limit reached: More than 2 instances of '$currentScriptFilename' are running. Terminating process.");
  exit;
}

// Create or open the lock file.
$lockFile = fopen($lockFilePath, 'w+');
if ($lockFile === false) {
  throw new RuntimeException("Failed to open or create the lock file: $lockFilePath");
}

$runAllowed = false;

// Attempt to acquire an exclusive lock to prevent multiple instances from running simultaneously
if (flock($lockFile, LOCK_EX)) {
  _log_shared($hashFilename ?? 'CLI', "$lockFilePath Lock acquired");
  $runAllowed = true;

  if (isset($proxy) && !empty($proxy)) {
    // Perform critical operations:
    // - Clear the log file before starting
    // - Execute the `check` function with the provided proxy
    truncateFile(get_log_file_shared($hashFilename ?? 'CLI'));
    check($proxy);
  }

  // Release the lock after completing the critical section
  flock($lockFile, LOCK_UN);
  if ($isAdmin) {
    _log_shared($hashFilename ?? 'CLI', "$lockFilePath Lock released");
  } else {
    _log_shared($hashFilename ?? 'CLI', 'Lock released');
  }
} else {
  // Another process holds the lock; skip execution
  _log_shared($hashFilename ?? 'CLI', 'Another process is still running');
  $runAllowed = false;
}

// Close the lock file handle to free system resources
fclose($lockFile);

if ($runAllowed) {
  // Delete the lock file after successful execution
  delete_path($lockFilePath);
}

// logging and log-file helpers are provided by checker-runner.php

/**
 * Check if the proxy is working
 * @param string $proxy proxy string
 */
function check(string $proxy) {
  global $proxy_db, $hashFilename, $isAdmin, $isCli;
  $proxies = extractProxies($proxy, $proxy_db, true);
  shuffle($proxies);

  $count       = count($proxies);
  $logFilename = $hashFilename ?? 'CLI';
  _log_shared($hashFilename ?? 'CLI', trim('[' . ($isCli ? 'CLI' : 'WEB') . '][' . ($isAdmin ? 'admin' : 'user') . '] ' . substr($logFilename, 0, 6) . " Checking $count proxies..."));

  // Record the start time
  $startTime            = microtime(true);
  $limitSecs            = 120;
  $isExecutionTimeLimit = function () use ($startTime, $limitSecs) {
    // Check if the script has been running for more than [n] seconds
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

    // Check if the script has been running for more than [n] seconds
    if (!$isAdmin && $isExecutionTimeLimit()) {
      break;
    }

    // Check if last checked time is more than [n] hour(s)
    $expired = $item->last_check ? isDateRFC3339OlderThanHours($item->last_check, 5) : true;

    // Skip already SSL-supported proxy
    if ($item->https == 'true' && $item->status == 'active' && !$expired) {
      _log_shared($hashFilename ?? 'CLI', "[$no] Skipping proxy {$item->proxy}: Already supports SSL and is active.");
      continue;
    } elseif ($item->last_check) {
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

    // Skip already checked proxy by file-based locking
    // $lockProxyFile = tmp() . '/runners/already-checked-proxies/' . $item->proxy . '.txt';
    // if (file_exists($lockProxyFile)) {
    //   _log("[$no] Skipping proxy {$item->proxy}: recently checked at {$item->last_check}.");
    //   continue;
    // }

    $ssl_protocols = [];
    $protocols     = ['http', 'socks4', 'socks5'];
    $latencies     = [];
    foreach ($protocols as $protocol) {
      $curl = buildCurl($item->proxy, $protocol, 'https://support.mozilla.org/en-US/', [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
      ]);
      $result = curl_exec($curl);
      $msg    = "[$no] $protocol://{$item->proxy} ";

      if ($result) {
        $info = curl_getinfo($curl);
        curl_close($curl);
        if ($info['http_code'] == 200) {
          $msg .= round($info['total_time'], 2) . 's ';
          // Get the time taken for the request in milliseconds
          $latencies[] = round($info['total_time'] * 1000, 2);

          if (checkRawHeadersKeywords($result)) {
            $msg .= 'SSL dead (Azenv). ';
          } else {
            preg_match("/<title>(.*?)<\/title>/is", $result, $matches);
            if (!empty($matches)) {
              $msg .= 'Title: ' . $matches[1];
              if (strtolower($matches[1]) == strtolower('Mozilla Support')) {
                $msg .= ' (VALID) ';
                $ssl_protocols[] = $protocol;
              } else {
                $msg .= ' (INVALID) ';
              }
            } else {
              $msg .= 'Title: N/A ';
            }
          }
        }
      } else {
        $msg .= 'SSL dead ';
      }

      _log_shared($hashFilename ?? 'CLI', trim($msg));
    }
    // Prepare the base data array
    $data = ['https' => !empty($ssl_protocols) ? 'true' : 'false', 'last_check' => date(DATE_RFC3339)];

    // If ssl_protocols are available, add the corresponding fields
    if (!empty($ssl_protocols)) {
      $data['type']   = join('-', $ssl_protocols);
      $data['status'] = 'active';

      // Add the highest latency if available
      if (!empty($latencies)) {
        $data['latency'] = max($latencies);
      }
    }

    // Perform the database update
    $proxy_db->updateData($item->proxy, $data);

    // Write lock proxy file
    // $date = new DateTime();
    // $date_rfc3339 = $date->format('Y-m-d\TH:i:sP');
    // write_file($lockProxyFile, $date_rfc3339);
    // Build a friendly per-proxy log line (single summary)
    $statusSymbol = (!empty($ssl_protocols) && count($ssl_protocols) > 0) ? '[OK]' : '[--]';
    $protocolsStr = !empty($ssl_protocols) ? implode(',', $ssl_protocols) : '';
    $latencyStr   = '';
    if (!empty($latencies)) {
      // Use max latency already stored in $data if available, otherwise compute
      $lat        = isset($data['latency']) ? $data['latency'] : max($latencies);
      $latencyStr = round($lat / 1000, 2) . 's';
    }

    $lineParts = ["[$no]", $statusSymbol, $item->proxy];
    if ($protocolsStr !== '') {
      $lineParts[] = 'protocols=' . $protocolsStr;
    }
    if ($latencyStr !== '') {
      $lineParts[] = 'latency=' . $latencyStr;
    }

    _log_shared($hashFilename ?? 'CLI', implode(' ', $lineParts));
  }

  _log_shared($hashFilename ?? 'CLI', 'Done checking proxies.');
}
