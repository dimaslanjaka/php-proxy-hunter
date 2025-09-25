<?php

declare(strict_types=1);

include __DIR__ . '/shared.php';

use PhpProxyHunter\ProxyDB;

global $isAdmin, $isCli;

/**
 * NOTE:
 * - I preserved all original functions and PHPDoc comments (addLog, resetLog, getLogFile,
 *   getWebsiteTitle, getPublicIP, send_json, send_text).
 * - Optimization changes are internal (null coalescing, early returns, small helpers,
 *   less repetition, consistent checks, safer file writes).
 */

/** Ensure directory exists helper (keeps behavior but avoids repeating mkdir checks) */
function ensure_dir(string $dir): void
{
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
  }
}

/** Small helper to safely unlink a file if it exists */
function safe_unlink(string $file): void
{
  if (file_exists($file)) {
    @unlink($file);
  }
}

/** Helper to get normalized request scheme/host/script dir */
function get_self_base(): string
{
  $scheme = $_SERVER['REQUEST_SCHEME'] ?? (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http');
  $host   = $_SERVER['HTTP_HOST']      ?? 'localhost';
  $script = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
  return rtrim($scheme . '://' . $host . $script, '/');
}

/** Small helper to return proxy details array and joined string to avoid repeating loops */
function build_proxy_details(array $proxyInfo): array
{
  $details = [];
  foreach (['proxy', 'type', 'username', 'password'] as $key) {
    if (!empty($proxyInfo[$key])) {
      $details[] = ucfirst($key) . ': ' . $proxyInfo[$key];
    }
  }
  return [
    'array' => $details,
    'text'  => implode(', ', $details),
  ];
}

/** ---------- bootstrap ---------- */

$db = new ProxyDB(__DIR__ . '/../src/database.sqlite');

$lockFilePath = tmp() . '/logs/user-' . getUserId() . '/proxyChecker.lock';
ensure_dir(dirname($lockFilePath));

if (!$isCli) {
  // Set CORS (Cross-Origin Resource Sharing) headers to allow requests from any origin
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: *');
  header('Access-Control-Allow-Methods: *');

  // Ignore browser caching
  header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');

  $request   = parseQueryOrPostBody();
  $proxyInfo = [
    'proxy'    => isset($request['proxy']) ? urldecode($request['proxy']) : null,
    'type'     => isset($request['type']) ? urldecode($request['type']) : null,
    'username' => isset($request['username']) ? urldecode($request['username']) : null,
    'password' => isset($request['password']) ? urldecode($request['password']) : null,
  ];

  $userId     = getUserId();
  $config     = getConfig($userId);
  $statusFile = getUserStatusFile($userId);

  // Handle type log and status requests
  if (isset($request['type'])) {
    if ($request['type'] === 'log') {
      $logFile = getLogFile();
      if (file_exists($logFile)) {
        send_text(file_get_contents($logFile));
      }
      send_json([
        'error'   => true,
        'message' => 'Log file not found.',
      ]);
    } elseif ($request['type'] === 'status') {
      if (file_exists($statusFile)) {
        send_text(file_get_contents($statusFile));
      }
      send_json([
        'error'   => true,
        'message' => 'Status file not found.',
      ]);
    }
  }

  // Handle reset log request
  if (isset($request['resetLog']) && $request['resetLog']) {
    resetLog();
    send_json([
      'error'   => false,
      'message' => 'Log file reset.',
    ]);
  }

  // Handle set timeout request
  if (isset($request['set_curl_timeout'])) {
    $timeout = intval($request['set_curl_timeout']);
    if ($timeout < 1 || $timeout > 300) {
      send_json([
        'error'   => true,
        'message' => 'Timeout must be between 1 and 300 seconds.',
      ]);
    }
    $config['curl_timeout'] = $timeout;
    setConfig($userId, $config);
    $config = getConfig($userId); // refresh
    send_json([
      'error'   => false,
      'message' => "cURL timeout set to $timeout seconds.",
    ]);
  }

  // Validate input before calling getPublicIP
  if (empty($proxyInfo['proxy']) && empty($proxyInfo['type'])) {
    // Ensure the directory exists before writing the file
    ensure_dir(dirname($statusFile));
    http_response_code(400);
    send_json([
      'error'   => true,
      'message' => "'proxy' and 'type' parameters are required.",
    ]);
  }

  // Check lock file
  if (file_exists($lockFilePath) && !$isAdmin) {
    // another process still running
    $status = 'Another process is still running.';
    http_response_code(429);
    send_json([
      'error'    => true,
      'message'  => $status,
      'lockFile' => $lockFilePath,
    ]);
  }

  if (!empty($proxyInfo['proxy'])) {
    $validTypes = ['http', 'https', 'socks4', 'socks5', 'socks5h'];
    if (!empty($proxyInfo['type']) && !in_array(strtolower($proxyInfo['type']), $validTypes, true)) {
      http_response_code(400);
      send_json([
        'error'   => true,
        'message' => 'Invalid proxy type. Supported types are http, https, socks4, socks5, socks5h.',
      ]);
    }

    // Run proxy check in background
    $script     = __FILE__;
    $cmdParts   = [];
    $cmdParts[] = 'php';
    $cmdParts[] = escapeshellarg($script);
    $cmdParts[] = '--userId=' . escapeshellarg($userId);
    if ($isAdmin) {
      $cmdParts[] = '--admin';
    }
    $cmdParts[] = '--proxy=' . escapeshellarg($proxyInfo['proxy']);
    if (!empty($proxyInfo['type'])) {
      $cmdParts[] = '--type=' . escapeshellarg($proxyInfo['type']);
    }
    if (!empty($proxyInfo['username'])) {
      $cmdParts[] = '--username=' . escapeshellarg($proxyInfo['username']);
    }
    if (!empty($proxyInfo['password'])) {
      $cmdParts[] = '--password=' . escapeshellarg($proxyInfo['password']);
    }
    $cmdParts[] = '--lockFile=' . escapeshellarg($lockFilePath);
    $cmdParts[] = '--runner=' . escapeshellarg(__FILE__);

    $output_file = __DIR__ . '/../proxyChecker.txt';
    $pid_file    = tmp() . '/runners/proxy-checker-' . getUserId() . '.pid';
    ensure_dir(dirname($pid_file));

    $cmd = implode(' ', $cmdParts);
    // run in background, record pid
    $cmdLine = sprintf('%s > %s 2>&1 & echo $! >> %s', $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));

    $isWin  = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $runner = __DIR__ . '/tmp/runners/proxyChecker' . ($isWin ? '.bat' : '');
    ensure_dir(dirname($runner));

    write_file($runner, $cmdLine);
    runBashOrBatch($runner);

    // Build embed URLs for log and status files
    $selfBase       = get_self_base();
    $id             = urlencode($userId);
    $logEmbedUrl    = $selfBase . '/proxy-checker.php?id=' . $id . '&type=log';
    $statusEmbedUrl = $selfBase . '/proxy-checker.php?id=' . $id . '&type=status';

    // Return a response indicating that the check is in progress
    send_json([
      'error'          => false,
      'message'        => 'Proxy check is in progress. Please check back later.',
      'logEmbedUrl'    => $logEmbedUrl,
      'statusEmbedUrl' => $statusEmbedUrl,
    ]);
    // Note: The actual proxy checking will be done in the background process.
  }
} else {
  $short_opts = 'p:m::';
  $long_opts  = [
    'proxy:',
    'max::',
    'userId::',
    'lockFile::',
    'runner::',
    'admin::',
    'type::',
    'username::',
    'password::',
  ];
  $options = getopt($short_opts, $long_opts);

  if (isset($options['admin'])) {
    $isAdmin = true;
  }
  if (isset($options['userId'])) {
    setUserId($options['userId']);
  }

  $userId     = getUserId();
  $config     = getConfig($userId);
  $statusFile = getUserStatusFile($userId);
  $lockFile   = $options['lockFile'] ?? $lockFilePath;
  $proxyInfo  = [
    'proxy'    => $options['proxy']    ?? null,
    'type'     => $options['type']     ?? null,
    'username' => $options['username'] ?? null,
    'password' => $options['password'] ?? null,
  ];
  $timeout = $config['curl_timeout'] ?? 10;

  if (file_exists($lockFile) && !$isAdmin) {
    // another process still running
    $status = 'Another process is still running.';
    @file_put_contents($statusFile, $status . PHP_EOL, LOCK_EX);
    exit($status . PHP_EOL);
  }

  // create lock file
  file_put_contents($lockFile, (string)getmypid(), LOCK_EX);

  // ensure lock file is always deleted when script ends
  register_shutdown_function(function () use ($lockFile, $db, $proxyInfo) {
    $working_proxies = parse_working_proxies($db);
    $projectRoot     = __DIR__ . '/..';
    // write working proxies
    write_file($projectRoot . '/working.txt', $working_proxies['txt']);
    write_file($projectRoot . '/working.json', json_encode($working_proxies['array']));
    write_file($projectRoot . '/status.json', json_encode($working_proxies['counter']));
    safe_unlink($lockFile);
    $proxyDetails = [];
    foreach (['proxy', 'type', 'username', 'password'] as $key) {
      if (!empty($proxyInfo[$key])) {
        $proxyDetails[] = ucfirst($key) . ': ' . $proxyInfo[$key];
      }
    }
    addLog('Process ended, lock file removed.' . (count($proxyDetails) ? ' (' . implode(', ', $proxyDetails) . ')' : ''));
  });

  // validate input before calling getPublicIP
  if (empty($proxyInfo['proxy']) && empty($proxyInfo['type'])) {
    // Ensure the directory exists before writing the file
    ensure_dir(dirname($statusFile));
    $status = "'proxy' and 'type' parameters are required.";
    @file_put_contents($statusFile, $status . PHP_EOL, LOCK_EX);
    safe_unlink($lockFile);
    exit($status . PHP_EOL);
  }

  // if empty proxy type, loop through all types
  if (empty($proxyInfo['type'])) {
    $proxyTypes   = ['http', 'https', 'socks4', 'socks5', 'socks5h'];
    $foundWorking = false;
    @file_put_contents($statusFile, 'starting', LOCK_EX);
    foreach ($proxyTypes as $type) {
      $proxyInfo['type'] = $type;
      @file_put_contents($statusFile, 'processing', LOCK_EX);
      $proxyDetailsArr = build_proxy_details($proxyInfo);
      addLog('Checking proxy (' . $proxyDetailsArr['text'] . ')...');

      $publicIP        = getPublicIP(true, $timeout, $proxyInfo);
      $proxyDetailsArr = build_proxy_details($proxyInfo);

      if (empty($publicIP)) {
        // Mark as dead if no public IP found for this type
        $db->updateData($proxyInfo['proxy'], ['status' => 'dead', 'last_check' => date(DATE_RFC3339)]);
        addLog('Proxy is dead (no public IP detected) (' . $proxyDetailsArr['text'] . ')');
        continue;
      }

      $ipProxy = extractIPs($proxyInfo['proxy']);
      if (in_array($publicIP, $ipProxy, true)) {
        // Proxy working, same as proxy IP
        $db->updateData($proxyInfo['proxy'], ['status' => 'active', 'last_check' => date(DATE_RFC3339)]);
        $resultMessage = "Proxy is working. Detected IP: $publicIP (" . $proxyDetailsArr['text'] . ')';
        // Check website title to verify proxy functionality
        $titleOk = getWebsiteTitle(null, null, true, $timeout, $proxyInfo);
        $resultMessage .= $titleOk ? ' Website title check passed.' : ' Website title check failed.';
        addLog($resultMessage);
        $foundWorking = true;
      } else {
        // Proxy working, but different IP
        $resultMessage = "Proxy is working, but detected IP ($publicIP) does not match proxy IP. (" . $proxyDetailsArr['text'] . ')';
        addLog($resultMessage);
        $db->updateData($proxyInfo['proxy'], ['status' => 'unknown', 'last_check' => date(DATE_RFC3339)]);
      }
    }
    @file_put_contents($statusFile, 'stopped', LOCK_EX);
  } else {
    // Run the proxy check with specified type
    @file_put_contents($statusFile, 'starting', LOCK_EX);
    $proxyDetailsArr = build_proxy_details($proxyInfo);
    addLog('Checking proxy (' . $proxyDetailsArr['text'] . ')');

    @file_put_contents($statusFile, 'processing', LOCK_EX);
    $publicIP = getPublicIP(true, $timeout, $proxyInfo);
    if (empty($publicIP)) {
      // Mark as dead if no public IP found
      $db->updateData($proxyInfo['proxy'], ['status' => 'dead', 'last_check' => date(DATE_RFC3339)]);
      addLog('Proxy is dead (no public IP detected) (' . $proxyDetailsArr['text'] . ')');
      @file_put_contents($statusFile, 'stopped', LOCK_EX);
      exit('stopped' . PHP_EOL);
    }

    $ipProxy = extractIPs($proxyInfo['proxy']);
    if (in_array($publicIP, $ipProxy, true)) {
      // Proxy working, same as proxy IP
      $db->updateData($proxyInfo['proxy'], ['status' => 'active', 'last_check' => date(DATE_RFC3339)]);
      $status = "Proxy is working. Detected IP: $publicIP (" . $proxyDetailsArr['text'] . ')';
      // Check website title to verify proxy functionality
      $titleOk = getWebsiteTitle(null, null, true, 300, $proxyInfo);
      $status .= $titleOk ? ' Website title check passed.' : ' Website title check failed.';
      addLog($status);
      @file_put_contents($statusFile, 'stopped', LOCK_EX);
      exit('stopped' . PHP_EOL);
    } else {
      // Proxy working, but different IP
      $status = "Proxy is working, but detected IP ($publicIP) does not match proxy IP. (" . $proxyDetailsArr['text'] . ')';
      addLog($status);
      $db->updateData($proxyInfo['proxy'], ['status' => 'unknown', 'last_check' => date(DATE_RFC3339)]);
      @file_put_contents($statusFile, 'stopped', LOCK_EX);
      exit('stopped' . PHP_EOL);
    }
  }
}

/**
 * Write a log entry for the current user.
 *
 * @param string $message
 * @return void
 */
function addLog($message)
{
  global $isCli;
  $logFile = getLogFile();
  $logDir  = dirname($logFile);
  if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
  }
  $timestamp = date(DATE_RFC3339);
  $logEntry  = "[$timestamp] $message" . PHP_EOL;
  @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
  if ($isCli) {
    echo $logEntry;
  }
}

/**
 * Reset the log file for the current user.
 *
 * @return void
 */
function resetLog()
{
  $logFile = getLogFile();
  if (file_exists($logFile)) {
    $timestamp = date(DATE_RFC3339);
    $logEntry  = "[$timestamp] Log reset" . PHP_EOL;
    @file_put_contents($logFile, $logEntry, LOCK_EX);
  }
}

/**
 * Get the log file path for the current user.
 *
 * @return string
 */
function getLogFile()
{
  return tmp() . '/logs/user-' . getUserId() . '/proxy-checker.log';
}

/**
 * Check if the HTML <title> of a page matches the expected title.
 *
 * @param string $url The URL to check
 * @param string $title The expected title
 * @param bool $cache Enable/disable caching (not used)
 * @param int $cacheTimeout Cache timeout in seconds (not used)
 * @param array $proxyInfo Proxy info (same as getPublicIP)
 * @return bool True if the page title matches, false otherwise
 */
function getWebsiteTitle($url = null, $title = null, $cache = false, $cacheTimeout = 300, $proxyInfo = [])
{
  // Set default values if not provided
  if (!$url) {
    $url = 'https://support.mozilla.org/en-US/';
  }
  if (!$title) {
    $title = 'Mozilla Support';
  }
  // Cache logic
  $cacheDir = tmp() . '/runners/website-title';
  if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
  }

  $cacheKey  = md5($url . $title . ($proxyInfo['proxy'] ?? '') . ($proxyInfo['type'] ?? '') . ($proxyInfo['username'] ?? '') . ($proxyInfo['password'] ?? ''));
  $cacheFile = $cacheDir . '/' . $cacheKey . '.cache';
  if ($cache) {
    if (file_exists($cacheFile)) {
      $data = @json_decode(@file_get_contents($cacheFile), true);
      if (is_array($data) && isset($data['result'], $data['expires']) && $data['expires'] > time()) {
        return (bool)$data['result'];
      }
    }
  }

  $ch = buildCurl(
    $proxyInfo['proxy'] ?? null,
    $proxyInfo['type']  ?? 'http',
    $url,
    ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0'],
    $proxyInfo['username'] ?? null,
    $proxyInfo['password'] ?? null
  );

  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

  $output   = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if ($output === false) {
    $curlError = curl_error($ch);
    $proxyStr  = $proxyInfo['proxy'] ?? 'N/A';
    $proxyType = $proxyInfo['type']  ?? 'N/A';
    addLog("getWebsiteTitle CURL error: $curlError (URL: $url, Proxy: $proxyStr, Type: $proxyType)");
  }

  curl_close($ch);

  $result = false;
  if ($httpCode >= 200 && $httpCode < 300 && $output) {
    if (preg_match('/<title>(.*?)<\/title>/is', $output, $matches)) {
      $pageTitle = trim($matches[1]);
      $result    = ($pageTitle === $title);
    }
  }

  if ($cache) {
    $data = [
      'result'  => $result,
      'expires' => time() + $cacheTimeout,
    ];
    @file_put_contents($cacheFile, json_encode($data));
  }

  return $result;
}

/**
 * Get the public IP address using multiple services, with optional proxy support and simple file cache.
 *
 * @param bool $cache Enable/disable caching
 * @param int $cacheTimeout Cache timeout in seconds
 * @param array $proxyInfo Optional proxy info: [
 *   'proxy' => string, // proxy address
 *   'type' => string,  // proxy type (http, socks5, etc)
 *   'username' => string|null,
 *   'password' => string|null
 * ]
 * @return string
 */
function getPublicIP($cache = false, $cacheTimeout = 300, $proxyInfo = [])
{
  $ipServices = [
    'https://api64.ipify.org',
    'https://ipinfo.io/ip',
    'https://api.myip.com',
    'https://ip.42.pl/raw',
    'https://ifconfig.me/ip',
    'https://cloudflare.com/cdn-cgi/trace',
    'https://httpbin.org/ip',
    'https://api.ipify.org',
  ];

  $cacheDir = tmp() . '/runners/public-ip';
  if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
  }

  $cacheKey = ($proxyInfo['proxy'] ?? '') !== ''
    ? md5(($proxyInfo['proxy'] ?? '') . ($proxyInfo['type'] ?? '') . ($proxyInfo['username'] ?? '') . ($proxyInfo['password'] ?? ''))
    : '';
  $cacheFile = $cacheDir . '/' . $cacheKey . '.cache';

  if ($cache && $cacheKey !== '') {
    if (file_exists($cacheFile)) {
      $data = @json_decode(@file_get_contents($cacheFile), true);
      if (is_array($data) && isset($data['ip'], $data['expires']) && $data['expires'] > time()) {
        return (string)$data['ip'];
      }
    }
  }

  $response = null;

  foreach ($ipServices as $idx => $url) {
    addLog('Trying IP service #' . ($idx + 1) . ' (Proxy: ' . ($proxyInfo['proxy'] ?? 'N/A') . ', Type: ' . ($proxyInfo['type'] ?? 'N/A') . ')');
    $ch = buildCurl(
      $proxyInfo['proxy'] ?? null,
      $proxyInfo['type']  ?? 'http',
      $url,
      ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0'],
      $proxyInfo['username'] ?? null,
      $proxyInfo['password'] ?? null
    );

    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $output   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && $output) {
      $response = $output;
      break;
    }
  }

  if (!$response) {
    return '';
  }

  // Parse IP using regex
  if (preg_match(
    "/(?!0)(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/",
    $response,
    $matches
  )) {
    $result = $matches[0] ?? '';
    if ($result !== '') {
      if ($cache && $cacheKey !== '') {
        $data = [
          'ip'      => $result,
          'expires' => time() + $cacheTimeout,
        ];
        @file_put_contents($cacheFile, json_encode($data));
      }
      return (string)$result;
    }
  }

  return '';
}

/** Helper to send JSON response and exit */
function send_json($data)
{
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

function send_text($text)
{
  header('Content-Type: text/plain; charset=utf-8');
  echo $text;
  exit;
}
