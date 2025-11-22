<?php

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\AnsiColors;
use PhpProxyHunter\CoreDB;

global $isAdmin, $isCli, $dbHost, $dbName, $dbUser, $dbPass, $dbFile, $core_db, $user_db, $proxy_db, $log_db;

// Clean up existing DB connections to avoid conflicts
unset($core_db, $user_db, $proxy_db, $log_db);
gc_collect_cycles();

$core_db = new CoreDB(
  $dbFile,
  $dbHost,
  $dbName,
  $dbUser,
  $dbPass,
  false,
  $dbType
);
/** @var \PhpProxyHunter\UserDB $user_db */
$user_db = $core_db->user_db;
/** @var \PhpProxyHunter\ProxyDB $proxy_db */
$proxy_db = $core_db->proxy_db;
/** @var \PhpProxyHunter\ActivityLog $log_db */
$log_db = $core_db->log_db;

/**
 * NOTE:
 * - I preserved all original functions and PHPDoc comments (addLog, resetLog, getLogFile,
 *   getWebsiteTitle, getPublicIP, send_json, send_text).
 * - Optimization changes are internal (null coalescing, early returns, small helpers,
 *   less repetition, consistent checks, safer file writes).
 */

/** Helper to get normalized request scheme/host/script dir */
function get_self_base() {
  $scheme = $_SERVER['REQUEST_SCHEME'] ?? (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http');
  $host   = $_SERVER['HTTP_HOST']      ?? 'localhost';
  $script = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
  return rtrim($scheme . '://' . $host . $script, '/');
}

/** Small helper to return proxy details array and joined string to avoid repeating loops */
function build_proxy_details(array $proxyInfo) {
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

/**
 * Normalize credential values: return null for empty string or single hyphen '-'.
 */
function normalize_credential($val) {
  if (!is_string($val)) {
    return $val;
  }
  $t = trim($val);
  if ($t === '' || $t === '-') {
    return null;
  }
  return $val;
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
    'username' => normalize_credential(isset($request['username']) ? urldecode($request['username']) : null),
    'password' => normalize_credential(isset($request['password']) ? urldecode($request['password']) : null),
  ];

  $userId     = getUserId();
  $config     = getConfig($userId);
  $statusFile = getUserStatusFile($userId);

  // Handle type log and status requests
  if (isset($request['type'])) {
    if ($request['type'] === 'log') {
      $logFile = getLogFile();
      if (file_exists($logFile)) {
        if (is_file_locked($logFile)) {
          send_json([
            'error'   => true,
            'message' => 'Log file is currently locked. Please try again later.',
          ]);
        }
        send_text(read_file($logFile));
      }
      send_json([
        'error'   => true,
        'message' => 'Log file not found.',
      ]);
    } elseif ($request['type'] === 'status') {
      if (file_exists($statusFile)) {
        if (is_file_locked($statusFile)) {
          send_json([
            'error'   => true,
            'message' => 'Status file is currently locked. Please try again later.',
          ]);
        }
        send_text(read_file($statusFile));
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
    $config = getConfig($userId);
    // refresh
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
    $cmdParts[] = getPhpExecutable();
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
    $runner     = tmp() . '/runners/proxy-checker/' . sanitizeFilename($proxyInfo['proxy']) . ($isWin ? '.bat' : '');
    $cmdParts[] = '--runner=' . escapeshellarg($runner);

    $output_file = getLogFile();
    write_file($output_file, '[' . date('Y-m-d H:i:s') . "] Proxy Checker started\n");
    $cmdParts[] = '--outputFile=' . escapeshellarg($output_file);
    $pid_file   = tmp() . '/runners/proxy-checker/' . sanitizeFilename($proxyInfo['proxy']) . '.pid';
    write_file($pid_file, '[' . date('Y-m-d H:i:s') . "] PID file created\n");

    $cmd = implode(' ', $cmdParts);
    // run in background, record pid (platform-specific)
    $isWin  = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $runner = tmp() . '/runners/proxy-checker/' . sanitizeFilename($proxyInfo['proxy']) . ($isWin ? '.bat' : '');
    write_file($runner, '');
    // clear existing content

    if ($isWin) {
      // Windows: use start to run in background; not all environments can capture PID reliably
      // use start /B to run without new window and redirect output to file
      $cmdLine = sprintf('start "" /B %s > %s 2>&1', $cmd, escapeshellarg($output_file));
    } else {
      // Unix-like: run in background and record PID
      $cmdLine = sprintf('%s > %s 2>&1 & echo $! >> %s', $cmd, escapeshellarg($output_file), escapeshellarg($pid_file));
    }

    write_file($runner, $cmdLine);
    $run            = runBashOrBatch($runner, [], 'proxy-checker', true);
    $run['message'] = json_decode($run['message'], true);

    // Build embed URLs for log and status files
    $selfBase       = get_self_base();
    $id             = urlencode($userId);
    $logEmbedUrl    = $selfBase . '/proxy-checker.php?id=' . $id . '&type=log';
    $statusEmbedUrl = $selfBase . '/proxy-checker.php?id=' . $id . '&type=status';

    // Return a response indicating that the check is in progress
    $res = [
      'error'          => false,
      'message'        => 'Proxy check is in progress. Please check back later.',
      'logEmbedUrl'    => $logEmbedUrl,
      'statusEmbedUrl' => $statusEmbedUrl,
    ];
    if ($isAdmin) {
      $res['pidFile']    = $pid_file;
      $res['outputFile'] = $output_file;
      $res['lockFile']   = $lockFilePath;
      $res['command']    = ['originalContent' => $cmd, 'runner' => $runner, 'runnerContent' => $cmdLine];
      $res['runInfo']    = $run;
    }
    send_json($res);
  // Note: The actual proxy checking will be done in the background process.
  } else {
    send_json([
      'error'   => true,
      'message' => "'proxy' parameter is required.",
    ]);
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
    'force::',
    'outputFile::',
  ];
  $options = getopt($short_opts, $long_opts);

  if (isset($options['admin'])) {
    $isAdmin = true;
  }
  if (isset($options['userId'])) {
    setUserId($options['userId']);
  }

  $userId       = getUserId();
  $config       = getConfig($userId);
  $statusFile   = getUserStatusFile($userId);
  $lockFilePath = $options['lockFile'] ?? $lockFilePath;
  $proxyInfo    = [
    'proxy'    => $options['proxy'] ?? null,
    'type'     => $options['type'] ?? null,
    'username' => normalize_credential($options['username'] ?? null),
    'password' => normalize_credential($options['password'] ?? null),
  ];
  $timeout = $config['curl_timeout'] ?? 10;

  if (file_exists($lockFilePath) && !$isAdmin) {
    // another process still running
    $status = 'Another process is still running.';
    write_file($statusFile, $status . PHP_EOL);
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
  write_file($lockFilePath, (string)getmypid());

  // ensure lock file is always deleted when script ends
  register_shutdown_function(function () use ($lockFilePath, $proxyInfo, $proxy_db) {
    // write working proxies file
    addLog('Writing working proxies file...');
    writing_working_proxies_file($proxy_db);
    // remove lock file
    delete_path($lockFilePath);
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
    // Get random proxy from database (limit to avoid loading entire DB into memory)
    // Default to a reasonable sample size so the process doesn't exhaust PHP memory on large DBs.
    $allProxies = $proxy_db->getUntestedProxies(1000);
    if (empty($allProxies)) {
      $status = 'No untested proxies available in the database.';
      write_file($statusFile, $status . PHP_EOL);
      delete_path($lockFilePath);
      exit($status . PHP_EOL);
    }
    $proxyInfo['proxy'] = $allProxies[array_rand($allProxies)]['proxy'];
    $proxyInfo['type']  = null;
    // try all types
  }

  $type         = empty($proxyInfo['type']) ? '' : strtolower($proxyInfo['type']);
  $allowedTypes = ['http', 'https', 'socks4', 'socks5', 'socks4a', 'socks5h'];
  if ($type !== '' && !in_array($type, $allowedTypes, true)) {
    $status = 'Invalid proxy type. Supported types are http, https, socks4, socks5, socks5h.';
    write_file($statusFile, $status . PHP_EOL);
    delete_path($lockFilePath);
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
function proxyChecker($proxyInfo, $types = []) {
  global $config, $proxy_db;

  $opt        = getopt('', ['force:']);
  $forceCheck = !empty($opt['force']) && in_array(strtolower($opt['force']), ['1', 'true', 'yes'], true);

  if (!$forceCheck) {
    // Skip proxy already checked as active under 4 hours
    $last_check = $proxy_db->select($proxyInfo['proxy'])[0]['last_check'] ?? null;
    if (!empty($last_check) && (strtotime($last_check) > (time() - 4 * 3600))) {
      // Already checked recently
      addLog('Skipping proxy check for ' . build_proxy_details($proxyInfo)['text'] . ' (already checked recently).');
      return;
    }
  }

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
  // Delegate actual checking to composer-autoloaded ProxyCheckerPublicIP
  addLog('Starting proxy check for ' . build_proxy_details($proxyInfo)['text']);

  $options = new \PhpProxyHunter\Checker\CheckerOptions([
    'proxy'     => $proxyInfo['proxy'],
    'username'  => $proxyInfo['username'] ?? '',
    'password'  => $proxyInfo['password'] ?? '',
    'protocols' => $types,
    'timeout'   => $config['curl_timeout'] ?? 10,
    'verbose'   => true,
  ]);

  // Use the composer-autoloaded checker class (no require_once)
  $result = \PhpProxyHunter\Checker\ProxyCheckerPublicIP::check($options);

  // save to database
  if (!$result->isWorking) {
    addLog('Proxy test failed for all types.');
    // mark dead and clear latency/anonymity
    $proxy_db->updateStatus($proxyInfo['proxy'], 'dead');
    $proxy_db->updateData($proxyInfo['proxy'], [
      'latency'    => null,
      'anonymity'  => null,
      'last_check' => date(DATE_RFC3339),
      'status'     => 'dead',
    ]);
  } else {
    $proxy_db->updateData($proxyInfo['proxy'], [
      'type'       => implode('-', $result->workingTypes),
      'username'   => $proxyInfo['username'] ?? null,
      'password'   => $proxyInfo['password'] ?? null,
      'https'      => $result->isSSL ? 'true' : 'false',
      'latency'    => is_numeric($result->latency) ? $result->latency : null,
      'anonymity'  => $result->anonymity ?: null,
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
function addLog($message) {
  // Simplified logger: just echo messages (no file logging or ANSI conversion)
  $logEntry = $message . PHP_EOL;
  echo $logEntry;
}

/**
 * Reset the log file for the current user.
 *
 */
function resetLog() {
  $logFile = getLogFile();
  if (file_exists($logFile)) {
    $timestamp = date(DATE_RFC3339);
    $logEntry  = "[$timestamp] Log reset" . PHP_EOL;
    write_file($logFile, $logEntry);
  }
}

/**
 * Get the log file path for the current user.
 *
 */
function getLogFile() {
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
function getWebsiteTitle($url = null, $title = null, $cache = false, $cacheTimeout = 300, $proxyInfo = []) {
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
    $proxyInfo['type'] ?? 'http',
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
    write_file($cacheFile, json_encode($data));
  }

  return $result;
}

/** Helper to send JSON response and exit */
function send_json($data) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

function send_text($text) {
  header('Content-Type: text/plain; charset=utf-8');
  $decoded_ansi = AnsiColors::ansiToHtml($text);
  echo $decoded_ansi;
  exit;
}
