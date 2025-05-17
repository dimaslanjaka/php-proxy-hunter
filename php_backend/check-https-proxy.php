<?php

require_once __DIR__ . '/../func.php';
require_once __DIR__ . '/../func-proxy.php';

use \PhpProxyHunter\ProxyDB;
use \PhpProxyHunter\Scheduler;
use \PhpProxyHunter\Server;

global $isCli;

$isAdmin = $isCli;

if (!$isCli) {
  // Turn off output buffering
  while (ob_get_level() > 0) {
    ob_end_flush();
  }
  ob_implicit_flush(true);

  // Set CORS (Cross-Origin Resource Sharing) headers to allow requests from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");

  // Set content type to plain text with UTF-8 encoding
  header('Content-Type: text/plain; charset=utf-8');

  // Disable browser caching
  header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');

  // Set user ID from request if available
  $req = parseQueryOrPostBody();
  if (isset($req['uid'])) {
    setUserId($req['uid']);
  }

  // Deny access if Google Analytics cookie is not present
  if (!isset($_COOKIE['_ga'])) {
    exit('Access Denied');
  }

  // Check if the user has admin privileges
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

$db = new ProxyDB(__DIR__ . '/../src/database.sqlite');
$userId = getUserId();
$request = parseQueryOrPostBody();
$currentScriptFilename = basename(__FILE__, '.php');
$full_url = Server::getCurrentUrl(true);

// Set maximum execution time to [n] seconds
ini_set('max_execution_time', 300);
if (function_exists('set_time_limit')) {
  // Resets the timer and sets the max execution time to 300 seconds
  call_user_func('set_time_limit', 300);
}

if (!$isCli) {
  if (isset($request['proxy'])) {
    // Generate a unique hash filename based on the script and user ID
    $hashFilename = "$currentScriptFilename-" . $userId;

    // Web server setup and process lock
    $webServerLock = tmp() . "/runners/$hashFilename.proc"; // Define a lock file path

    // Handle proxy configuration
    $proxy = $request['proxy']; // Extract proxy information from the request

    $proxy_file = tmp() . "/proxies/$hashFilename.txt"; // Define the path for the proxy file
    write_file($proxy_file, $proxy); // Save the proxy details to the file

    // Prepare for a long-running background process
    $file = __FILE__; // Get the current script's filename
    $output_file = tmp() . "/logs/$hashFilename.out"; // Define the path for the output log file
    setMultiPermissions([$file, $output_file], true); // Set appropriate permissions for the script and log file

    // Determine if the system is Windows
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    // Build the base command to run the PHP script
    $cmd = "php " . escapeshellarg($file);
    // Add the user ID as a CLI argument
    $cmd .= " --userId=" . escapeshellarg($userId);
    // Add the proxy file path as a CLI argument
    $cmd .= " --file=" . escapeshellarg($proxy_file);
    // Add the admin flag as a CLI argument (true/false)
    $cmd .= " --admin=" . escapeshellarg($isAdmin ? 'true' : 'false');
    // Add the --lockFile argument with the escaped web server lock file path
    $cmd .= " --lockFile=" . escapeshellarg(unixPath($webServerLock));
    // Trim any leading/trailing whitespace from the full command
    $cmd = trim($cmd);

    echo $cmd . "\n\n";

    // Redirect output and errors to a log file
    $cmd = sprintf("%s > %s 2>&1", $cmd, escapeshellarg($output_file));

    // Create a runner script for the command
    $runner = tmp() . "/runners/$hashFilename" . ($isWin ? '.bat' : ""); // Use .bat for Windows, no extension for others
    write_file($runner, $cmd); // Write the command to the runner script

    // Execute the runner script in the background
    runBashOrBatch($runner);
    exit; // Exit the current script
  } else {
    // Direct access
    echo "Usage:" . PHP_EOL;
    echo "\tcurl -X POST $full_url -d \"proxy=72.10.160.171:24049\"" . PHP_EOL;
    exit;
  }
} else {
  // Parse command line options (short: -f, -p; long: --file, --proxy, --admin), all optional
  $options = getopt("f::p::", ["file::", "proxy::", "admin::", "lockFile::"]);

  // Set admin flag if --admin is provided and is not 'false'
  $isAdmin = !empty($options['admin']) && $options['admin'] !== 'false';

  if (!empty($options['lockFile'])) {
    $lockFile = $options['lockFile'];

    // If the lock file exists, exit with an error message
    if (!$isAdmin && file_exists($lockFile)) {
      $lockedMsg = date(DATE_RFC3339) . " another process still running ({$lockFile} is locked) ";
      _log($lockedMsg);
      exit($lockedMsg);
    }

    // Create the lock file to prevent concurrent execution
    write_file($lockFile, '');

    // Always schedule the removal of the lock file after the process completes
    Scheduler::register(function () use ($lockFile) {
      delete_path($lockFile); // Remove the lock file
    }, 'release-cli-lock');
  }

  // Determine the file path from either -f or --file
  $file = isset($options['f']) ? $options['f'] : (isset($options['file']) ? $options['file'] : null);

  // Create a filename hash identifier (used for output/processing)
  $hashFilename = basename($file, '.txt');

  // Fallback filename if no valid file name is provided
  if ($hashFilename == '.txt' || empty($hashFilename)) {
    $hashFilename = "$currentScriptFilename-cli.txt";
  }

  // Determine the proxy source from either -p or --proxy
  $proxy = isset($options['p']) ? $options['p'] : (isset($options['proxy']) ? $options['proxy'] : null);

  // If no proxy file or string is provided, fallback to loading from the database
  if (!$file && !$proxy) {
    _log("No proxy file provided. Searching for proxies in database.");

    // Merge working and untested proxies from the database
    $proxiesDb = array_merge($db->getWorkingProxies(100), $db->getUntestedProxies(100));

    // Filter proxies: keep non-active (dead) or non-SSL ones
    $filteredArray = array_filter($proxiesDb, function ($item) {
      // Keep dead proxies
      if (strtolower($item['status']) != 'active') {
        return true;
      }
      // Only pick non-SSL proxies
      return strtolower($item['https']) != 'true';
    });

    // Extract proxy strings from the filtered result
    $proxyArray = array_map(function ($item) {
      return $item['proxy'];
    }, $filteredArray);

    // Convert proxy list to JSON string
    $proxy = json_encode($proxyArray);
  } elseif ($file) {
    // If a file is provided, read the content as the proxy list
    $read = read_file($file);
    if ($read) {
      $proxy = $read;
    }
  }
}

if (empty($hashFilename)) {
  $hashFilename = "CLI";
}
$lockFolder = unixPath(tmp() . '/runners/');
$lockFilePath = unixPath($lockFolder . $hashFilename . '.lock');
$lockFiles = glob($lockFolder . "/$currentScriptFilename*.lock");
// Check if the number of lock files exceeds the limit
if (count($lockFiles) > 2) {
  _log("Proxy checker process limit reached: More than 2 instances of '$currentScriptFilename' are running. Terminating process.");
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
  _log("$lockFilePath Lock acquired");
  $runAllowed = true;

  if (isset($proxy) && !empty($proxy)) {
    // Perform critical operations:
    // - Clear the log file before starting
    // - Execute the `check` function with the provided proxy
    truncateFile(get_log_file());
    check($proxy);
  }

  // Release the lock after completing the critical section
  flock($lockFile, LOCK_UN);
  if ($isAdmin) {
    _log("$lockFilePath Lock released");
  } else {
    _log("Lock released");
  }
} else {
  // Another process holds the lock; skip execution
  _log("Another process is still running");
  $runAllowed = false;
}

// Close the lock file handle to free system resources
fclose($lockFile);

if ($runAllowed) {
  // Delete the lock file after successful execution
  delete_path($lockFilePath);
}

function get_log_file()
{
  global $hashFilename;
  $_logFile = tmp() . "/logs/$hashFilename.txt";
  if (!file_exists($_logFile)) {
    file_put_contents($_logFile, "");
  }
  setMultiPermissions([$_logFile], true);
  return $_logFile;
}

/**
 * Logs messages to a file and outputs them to the console.
 *
 * @param mixed ...$args List of arguments to be logged. They are concatenated into a single string.
 * @return void
 */
function _log(...$args): void
{
  global $isCli;
  $_logFile = get_log_file();
  $message = join(" ", $args) . PHP_EOL;

  append_content_with_lock($_logFile, $message);
  echo $message;
  if (!$isCli) {
    // Push output to browser
    flush();
  }
}

/**
 * Check if the proxy is working
 * @param string $proxy proxy string
 */
function check(string $proxy)
{
  global $db, $hashFilename, $currentScriptFilename, $isAdmin, $isCli;
  $proxies = extractProxies($proxy, $db, true);
  shuffle($proxies);

  $count = count($proxies);
  $logFilename = str_replace("$currentScriptFilename-", "", $hashFilename);
  _log(trim('[' . ($isCli ? 'CLI' : 'WEB') . '][' . ($isAdmin ? 'admin' : 'user') . '] ' . substr($logFilename, 0, 6) . " Checking $count proxies..."));

  // Record the start time
  $startTime = microtime(true);
  $limitSecs = 120;
  $isExecutionTimeLimit = function () use ($startTime, $limitSecs) {
    // Check if the script has been running for more than [n] seconds
    $elapsedTime = microtime(true) - $startTime;
    if ($elapsedTime > $limitSecs) {
      _log("Proxy checker execution limit reached {$limitSecs}s.");
      return true;
    }
    return false;
  };

  for ($i = 0; $i < $count; $i++) {
    $no = $i + 1;
    $item = $proxies[$i];

    // Check if the script has been running for more than [n] seconds
    if (!$isAdmin && $isExecutionTimeLimit()) {
      break;
    }

    // Check if last checked time is more than [n] hour(s)
    $expired = $item->last_check ? isDateRFC3339OlderThanHours($item->last_check, 5) : true;

    // Skip already SSL-supported proxy
    if ($item->https == 'true' && $item->status == 'active' && !$expired) {
      _log("[$no] Skipping proxy {$item->proxy}: Already supports SSL and is active.");
      continue;
    } elseif ($item->last_check) {
      if ($item->status == 'dead') {
        if ($item->last_check && !$expired) {
          _log("[$no] Skipping proxy {$item->proxy}: Marked as dead, but was recently checked at {$item->last_check}.");
          continue;
        }
      } elseif ($item->https == 'false' && $item->status != 'untested') {
        if ($item->last_check && !$expired) {
          _log("[$no] Skipping proxy {$item->proxy}: Does not support SSL, but was recently checked at {$item->last_check}.");
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
    $protocols = ['http', 'socks4', 'socks5'];
    $latencies = [];
    foreach ($protocols as $protocol) {
      $curl = buildCurl($item->proxy, $protocol, 'https://www.ssl.org/', [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0'
      ]);
      $result = curl_exec($curl);
      $msg = "[$no] $protocol://{$item->proxy} ";

      if ($result) {
        $info = curl_getinfo($curl);
        curl_close($curl);
        if ($info['http_code'] == 200) {
          $msg .= round($info['total_time'], 2) . 's ';
          // Get the time taken for the request in milliseconds
          $latencies[] = round($info['total_time'] * 1000, 2);

          if (checkRawHeadersKeywords($result)) {
            $msg .= "SSL dead (Azenv). ";
          } else {
            preg_match("/<title>(.*?)<\/title>/is", $result, $matches);
            if (!empty($matches)) {
              $msg .= "Title: " . $matches[1];
              if (strtolower($matches[1]) == strtolower("SSL Certificate Checker")) {
                $msg .= " (VALID) ";
                $ssl_protocols[] = $protocol;
              } else {
                $msg .= " (INVALID) ";
              }
            } else {
              $msg .= "Title: N/A ";
            }
          }
        }
      } else {
        $msg .= "SSL dead ";
      }

      _log(trim($msg));
    }
    // Prepare the base data array
    $data = ['https' => !empty($ssl_protocols) ? 'true' : 'false', 'last_check' => date(DATE_RFC3339)];

    // If ssl_protocols are available, add the corresponding fields
    if (!empty($ssl_protocols)) {
      $data['type'] = join("-", $ssl_protocols);
      $data['status'] = 'active';

      // Add the highest latency if available
      if (!empty($latencies)) {
        $data['latency'] = max($latencies);
      }
    }

    // Perform the database update
    $db->updateData($item->proxy, $data);

    // Write lock proxy file
    // $date = new DateTime();
    // $date_rfc3339 = $date->format('Y-m-d\TH:i:sP');
    // write_file($lockProxyFile, $date_rfc3339);
  }

  _log("Done checking proxies.");
}
