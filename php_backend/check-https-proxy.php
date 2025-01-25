<?php

require_once __DIR__ . '/../func.php';
require_once __DIR__ . '/../func-proxy.php';

use \PhpProxyHunter\ProxyDB;

global $isCli, $isAdmin;

if (!$isCli) {
  // Set CORS (Cross-Origin Resource Sharing) headers to allow requests from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");

  // Set content type to TEXT with UTF-8 encoding
  header('Content-Type: text/plain; charset=utf-8');

  // Ignore browser caching
  header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');

  if (isset($_REQUEST['uid'])) {
    setUserId($_REQUEST['uid']);
  }
  // only allow user with Google Analytics cookie
  if (!isset($_COOKIE['_ga'])) {
    exit('Access Denied');
  }
  // check admin
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

$db = new ProxyDB(__DIR__ . '/../src/database.sqlite');
$userId = getUserId();
$request = parsePostData();
$currentScriptFilename = basename(__FILE__, '.php');

// Set maximum execution time to [n] seconds
ini_set('max_execution_time', 300);
if (function_exists('set_time_limit')) {
  // Resets the timer and sets the max execution time to 300 seconds
  call_user_func('set_time_limit', 300);
}

if (!$isCli && isset($request['proxy'])) {
  // Web server setup and process lock
  $webServerLock = tmp() . "/runners/$currentScriptFilename.proc"; // Define a lock file path
  if (file_exists($webServerLock)) {
    // Check if another process with the same script and user is running
    exit("Another process with same user id still running");
  } else {
    // Create the lock file to indicate the current process
    write_file($webServerLock, "");
  }

  // Handle proxy configuration
  $proxy = $request['proxy']; // Extract proxy information from the request
  $hashFilename = "$currentScriptFilename-" . $userId; // Generate a unique hash filename based on the script and user ID
  $proxy_file = tmp() . "/proxies/$hashFilename.txt"; // Define the path for the proxy file
  write_file($proxy_file, $proxy); // Save the proxy details to the file

  // Prepare for a long-running background process
  $file = __FILE__; // Get the current script's filename
  $output_file = tmp() . "/logs/$hashFilename.out"; // Define the path for the output log file
  setMultiPermissions([$file, $output_file], true); // Set appropriate permissions for the script and log file

  // Determine if the system is Windows
  $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

  // Build the command to execute the script
  $cmd = "php " . escapeshellarg($file); // Base command to run the current script
  $cmd .= " --userId=" . escapeshellarg($userId); // Append user ID as an argument
  $cmd .= " --file=" . escapeshellarg($proxy_file); // Append proxy file path as an argument
  $cmd = trim($cmd); // Trim any extra whitespace

  echo $cmd . "\n\n"; // Print the command for debugging purposes

  // Redirect output and errors to a log file
  $cmd = sprintf("%s > %s 2>&1", $cmd, escapeshellarg($output_file));

  // Create a runner script for the command
  $runner = tmp() . "/runners/$hashFilename" . ($isWin ? '.bat' : ""); // Use .bat for Windows, no extension for others
  write_file($runner, $cmd); // Write the command to the runner script

  // Execute the runner script in the background
  runBashOrBatch($runner);

  // Clean up by deleting the lock file
  delete_path($webServerLock);

  exit; // Exit the current script
} else {
  $options = getopt("f::p::", ["file::", "proxy::"]);

  $file = isset($options['f']) ? $options['f'] : (isset($options['file']) ? $options['file'] : null);
  $hashFilename = basename($file, '.txt');
  if ($hashFilename == '.txt' || empty($hashFilename)) {
    $hashFilename = "$currentScriptFilename-cli.txt";
  }
  $proxy = isset($options['p']) ? $options['p'] : (isset($options['proxy']) ? $options['proxy'] : null);

  if (!$file && !$proxy) {
    _log("No proxy file provided. Searching for proxies in database.");

    $proxiesDb = array_merge($db->getWorkingProxies(100), $db->getUntestedProxies(100));
    $filteredArray = array_filter($proxiesDb, function ($item) {
      // Keep dead proxies
      if (strtolower($item['status']) != 'active') {
        return true;
      }
      // Only pick non-SSL proxies
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
  $hashFilename = "CLI";
}
$lockFolder = tmp() . '/runners/';
$lockFilePath = $lockFolder . $hashFilename . '.lock';
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

// Attempt to acquire an exclusive lock to prevent multiple processes from running simultaneously.
if (flock($lockFile, LOCK_EX)) {
  _log("$lockFilePath Lock acquired");
  $runAllowed = true;

  // Perform the critical task:
  // - Clear the log file before starting the operation.
  // - Run the `check` function with the provided proxy.
  truncateFile(get_log_file());
  check($proxy);

  // Release the lock after completing the critical section.
  flock($lockFile, LOCK_UN);
  _log("Lock released");
} else {
  // Log that the lock is held by another process, preventing this one from proceeding.
  _log("Another process is still running");
}

// Close the lock file handle to release system resources.
fclose($lockFile);

if ($runAllowed) {
  // Delete the lock file to clean up after the process has finished.
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
  $_logFile = get_log_file();
  $message = join(" ", $args) . PHP_EOL;

  append_content_with_lock($_logFile, $message);
  echo $message;
}

/**
 * Check if the proxy is working
 * @param string $proxy proxy string
 */
function check(string $proxy)
{
  global $db, $hashFilename, $currentScriptFilename, $isAdmin;
  $proxies = extractProxies($proxy, $db, true);
  shuffle($proxies);

  $count = count($proxies);
  $logFilename = str_replace("$currentScriptFilename-", "", $hashFilename);
  _log(trim("$logFilename Checking $count proxies..."));

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
    if ($isExecutionTimeLimit() && !$isAdmin) {
      break;
    }

    // Check if last checked time is more than [n] hour(s)
    $expired = isDateRFC3339OlderThanHours($item->last_check, 5);

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
