<?php

include __DIR__ . '/shared.php';

use PhpProxyHunter\AnsiColors;

global $isAdmin, $isCli, $proxy_db;

/**
 * NOTE:
 * - I preserved all original functions and PHPDoc comments (addLog, resetLog, getLogFile,
 *   getWebsiteTitle, getPublicIP, send_json, send_text).
 * - Optimization changes are internal (null coalescing, early returns, small helpers,
 *   less repetition, consistent checks, safer file writes).
 */

/** Helper to get normalized request scheme/host/script dir */
function get_self_base()
{
  $scheme = $_SERVER['REQUEST_SCHEME'] ?? (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http');
  $host   = $_SERVER['HTTP_HOST']      ?? 'localhost';
  $script = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
  return rtrim($scheme . '://' . $host . $script, '/');
}

/** Small helper to return proxy details array and joined string to avoid repeating loops */
function build_proxy_details(array $proxyInfo)
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

$lockFilePath = tmp() . '/locks/user-' . getUserId() . '/php_backend/proxy-checker.lock';
ensure_dir(dirname($lockFilePath));

PhpProxyHunter\Server::allowCors(true);

if (!$isCli) {
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
      'message' => "'proxy' parameter is required.",
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
    $validTypes = ['http', 'https', 'socks4', 'socks5', 'socks4a', 'socks5h'];
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
    $runner     = __DIR__ . '/tmp/runners/proxyChecker' . ($isWin ? '.bat' : '');
    $cmdParts[] = '--runner=' . escapeshellarg($runner);

    $output_file = __DIR__ . '/../proxyChecker.txt';
    $pid_file    = tmp() . '/runners/proxy-checker-' . getUserId() . '.pid';
    ensure_dir(dirname($pid_file));

    $cmd = implode(' ', $cmdParts);
    // run in background, record pid
    $cmdLine = sprintf('%s > %s 2>&1 & echo $! >> %s', $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));

    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
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

  // Fix username and password empty string
  if (is_string($proxyInfo['username']) && trim($proxyInfo['username']) === '') {
    $proxyInfo['username'] = null;
  }
  if (is_string($proxyInfo['password']) && trim($proxyInfo['password']) === '') {
    $proxyInfo['password'] = null;
  }

  // create lock file
  file_put_contents($lockFile, (string)getmypid(), LOCK_EX);

  // ensure lock file is always deleted when script ends
  register_shutdown_function(function () use ($lockFile, $proxyInfo) {
    // Use the global $proxy_db inside shutdown handler via global keyword
    global $proxy_db;
    writing_working_proxies_file($proxy_db);
    // remove lock file
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

  $type         = empty($proxyInfo['type']) ? '' : strtolower($proxyInfo['type']);
  $allowedTypes = ['http', 'https', 'socks4', 'socks5', 'socks4a', 'socks5h'];
  if ($type !== '' && !in_array($type, $allowedTypes, true)) {
    $status = 'Invalid proxy type. Supported types are http, https, socks4, socks5, socks5h.';
    @file_put_contents($statusFile, $status . PHP_EOL, LOCK_EX);
    safe_unlink($lockFile);
    exit($status . PHP_EOL);
  }

  proxyChecker($proxyInfo, $type);
}

/**
 * Check a proxy by attempting to retrieve the public IP address through it.
 *
 * This function tests the provided proxy information against a list of proxy types (SSL and non-SSL)
 * by attempting to fetch the public IP address using external services. It logs the results of each
 * attempt and updates the proxy status in the database if a working configuration is found.
 *
 * @param array       $proxyInfo Array containing proxy details:
 *                               [
 *                                 'proxy'    => string Proxy address,
 *                                 'type'     => string|null Proxy type (http, socks5, etc),
 *                                 'username' => string|null Proxy username,
 *                                 'password' => string|null Proxy password
 *                               ]
 * @param string|array $types    Proxy type(s) to try (e.g. 'http', or ['http', 'socks5']).
 *
 * @return void
 */
function proxyChecker($proxyInfo, $types = [])
{
  global $config, $proxy_db;
  $currentIp = getServerIp();
  if (empty($currentIp)) {
    return;
  }
  $isCurrentIpIsLocal = preg_match('/^(192\.168|127\.)/', $currentIp) === 1 || is_debug_device();
  if ($isCurrentIpIsLocal) {
    // Try to get public IP if current IP is a common router IP
    $external_ip = getPublicIP(false, 10);
    if ($external_ip !== false && filter_var($external_ip, FILTER_VALIDATE_IP)) {
      $currentIp = $external_ip;
    }
  }
  addLog('Current server IP: ' . AnsiColors::colorize(['cyan'], $currentIp));
  if (is_string($types) && $types !== '') {
    $types = [$types];
  }
  if (empty($types)) {
    $types = ['http', 'https', 'socks4', 'socks5', 'socks4a', 'socks5h'];
  }
  $foundWorking = false;
  $isSSL        = false;
  $workingTypes = [];
  addLog('Starting ' . AnsiColors::colorize(['green', 'SSL']) . ' proxy check for ' . build_proxy_details($proxyInfo)['text']);
  $proxyIP = extractIPs($proxyInfo['proxy'])[0] ?? null;
  $timeout = $config['curl_timeout']            ?? 10;
  // Test SSL first
  foreach ($types as $type) {
    $publicIP = getPublicIP(true, $timeout, [
      'proxy'    => $proxyInfo['proxy'],
      'type'     => $type,
      'username' => $proxyInfo['username'],
      'password' => $proxyInfo['password'],
    ]);
    $ip = extractIPs($publicIP)[0] ?? null;
    if (empty($ip)) {
      addLog('Proxy SSL test ' . AnsiColors::colorize(['red'], 'failed') . ' for type ' . AnsiColors::colorize(['yellow'], $type) . ' (no IP returned).');
      continue; // try next type
    } else {
      if ($ip === $currentIp && $currentIp !== '127.0.0.1') {
        addLog('Proxy SSL test ' . AnsiColors::colorize(['red'], 'failed') . ' for type ' . AnsiColors::colorize(['yellow'], $type) . ' (IP matches current IP).');
        continue; // try next type
      } elseif ($ip === $proxyIP) {
        addLog('Proxy SSL test ' . AnsiColors::colorize(['green'], 'succeeded') . ' for type ' . AnsiColors::colorize(['yellow'], $type) . ' (IP: ' . AnsiColors::colorize(['cyan'], $ip) . ').');
        $foundWorking   = true;
        $isSSL          = true;
        $workingTypes[] = $type;
      } else {
        if ($ip !== $proxyIP && $ip !== $currentIp) {
          // High anonymous proxy detected
          addLog('Proxy SSL test ' . AnsiColors::colorize(['green'], 'succeeded') . ' for type ' . AnsiColors::colorize(['yellow'], $type) . ' (High anonymous, IP: ' . AnsiColors::colorize(['cyan'], $ip) . ').');
          $foundWorking   = true;
          $isSSL          = true;
          $workingTypes[] = $type;
        } else {
          addLog('Proxy SSL test ' . AnsiColors::colorize(['red'], 'failed') . ' for type ' . AnsiColors::colorize(['yellow'], $type) . ' (unexpected IP: ' . AnsiColors::colorize(['cyan'], $ip) . ').');
          continue; // try next type
        }
      }
    }
    $isLast = ($type === end($types));
    if ($isLast) {
      if (!$foundWorking) {
        addLog('Proxy SSL test failed for all types.');
      }
    }
  }
  // Test non-SSL only if SSL tests failed
  if (!$foundWorking) {
    foreach ($types as $type) {
      $timeout = $config['curl_timeout'] ?? 10;
      // Test non-SSL
      $publicIP = getPublicIP(true, $timeout, [
        'proxy'    => $proxyInfo['proxy'],
        'type'     => $type,
        'username' => $proxyInfo['username'],
        'password' => $proxyInfo['password'],
      ], true);
      $ip = extractIPs($publicIP)[0] ?? null;
      if (empty($ip)) {
        addLog('Proxy non-SSL test ' . AnsiColors::colorize(['red'], 'failed') . ' for type ' . AnsiColors::colorize(['yellow'], $type) . ' (no IP returned).');
        continue; // try next type
      } elseif (!empty($currentIp)) {
        if ($ip === $currentIp && $currentIp !== '127.0.0.1') {
          addLog('Proxy non-SSL test ' . AnsiColors::colorize(['red'], 'failed') . ' for type ' . AnsiColors::colorize(['yellow'], $type) . ' (IP matches current IP).');
          continue; // try next type
        } elseif ($ip === $proxyIP) {
          addLog('Proxy non-SSL test ' . AnsiColors::colorize(['green'], 'succeeded') . ' for type ' . AnsiColors::colorize(['yellow'], $type) . ' (IP: ' . AnsiColors::colorize(['cyan'], $ip) . ').');
          $foundWorking   = true;
          $isSSL          = false;
          $workingTypes[] = $type;
        } else {
          addLog('Proxy non-SSL test ' . AnsiColors::colorize(['red'], 'failed') . ' for type ' . AnsiColors::colorize(['yellow'], $type) . ' (unexpected IP: ' . AnsiColors::colorize(['cyan'], $ip) . ').');
          continue; // try next type
        }
      }
      $isLast = ($type === end($types));
      if ($isLast) {
        if (!$foundWorking) {
          addLog('Proxy non-SSL test failed for all types.');
        }
      }
    }
  }
  if (!$foundWorking) {
    addLog('Proxy test failed for all types.');
  } else {
    // save to database
    $proxy_db->updateData($proxyInfo['proxy'], [
      'type'       => implode('-', $workingTypes),
      'username'   => $proxyInfo['username'] ?? null,
      'password'   => $proxyInfo['password'] ?? null,
      'https'      => $isSSL ? 'true' : 'false',
      'last_check' => date(DATE_RFC3339),
      'status'     => 'active',
    ]);
  }
}

/**
 * Write a log entry for the current user.
 *
 * @param string $message
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
  @file_put_contents($logFile, AnsiColors::ansiToHtml($logEntry), FILE_APPEND | LOCK_EX);
  if ($isCli) {
    echo $logEntry;
  }
}

/**
 * Reset the log file for the current user.
 *
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
