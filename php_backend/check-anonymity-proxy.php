<?php

require_once __DIR__ . '/shared.php';
require_once __DIR__ . '/checker-runner.php';

use PhpProxyHunter\CoreDB;
use PhpProxyHunter\Scheduler;

global $isCli, $dbFile, $dbHost, $dbName, $dbUser, $dbPass, $dbType;

$isAdmin  = is_debug();
$core_db  = new CoreDB($dbFile, $dbHost, $dbName, $dbUser, $dbPass, false, $dbType);
$proxy_db = $core_db->proxy_db;

if (!$isCli) {
  PhpProxyHunter\Server::allowCors(true);
  header('Content-Type: text/plain; charset=utf-8');

  $req     = parseQueryOrPostBody();
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;

  // If proxy parameter provided via web, start a background runner similar to check-http-proxy
  if (isset($req['proxy'])) {
    $userId                = getUserId();
    $currentScriptFilename = basename(__FILE__, '.php');
    $hashFilename          = "$currentScriptFilename/$userId";
    $webServerLock         = tmp() . "/locks/$hashFilename.lock";
    $output_file           = tmp() . "/logs/$hashFilename.txt";
    $embedOutputUrl        = getFullUrl($output_file);

    // If lock exists and not stale (<2 minutes), reject
    if (file_exists($webServerLock)) {
      $mtime = filemtime($webServerLock);
      $age   = time() - $mtime;
      if ($age < 120) {
        respond_json(['error' => true, 'message' => '[Anonymity] Another process is still running. Please try again later.', 'logFile' => $embedOutputUrl]);
      } else {
        @unlink($webServerLock);
      }
    }

    $proxy = $req['proxy'];

    // Use short 8-character MD5 hash for concise identifiers/filenames
    $checksum      = substr(md5($proxy), 0, 8);
    $proxyFilename = 'added-' . basename(__FILE__, '.php') . "-$hashFilename-$checksum.txt";
    $proxy_file    = __DIR__ . "/../assets/proxies/$proxyFilename";
    write_file($proxy_file, $proxy);

    $file = __FILE__;
    setMultiPermissions([$file, $output_file], true);

    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    $cmd = getPhpExecutable(true) . ' ' . escapeshellarg($file);
    $cmd .= ' --file=' . escapeshellarg($proxy_file);
    $cmd .= ' --admin=' . escapeshellarg($isAdmin ? 'true' : 'false');
    $cmd .= ' --lockFile=' . escapeshellarg(unixPath($webServerLock));
    $cmd = trim($cmd);
    $cmd = sprintf('%s > %s 2>&1', $cmd, escapeshellarg($output_file));

    $runner = tmp() . "/runners/$hashFilename" . ($isWin ? '.bat' : '.sh');
    write_file($runner, $cmd);
    runBashOrBatch($runner);

    respond_json(['error' => false, 'message' => '[Anonymity] Check initiated.', 'logFile' => $embedOutputUrl]);
  }
}

// Default proxy value; will be set from CLI or loader
$proxy = null;

$protocols = ['http', 'socks4a', 'socks5h', 'socks4', 'socks5'];

// Lower = worse anonymity
$levels = [
  'transparent' => 1,
  'anonymous'   => 2,
  'elite'       => 3,
];

// CLI mode: parse options and implement lock handling
if ($isCli) {
  $options = getopt('f::p::', ['file::', 'proxy::', 'admin::', 'lockFile::']);
  $isAdmin = !empty($options['admin']) && $options['admin'] !== 'false';

  if (!empty($options['lockFile'])) {
    $lockFile = $options['lockFile'];
    if (!$isAdmin && file_exists($lockFile)) {
      $staleThreshold = 3600;
      $mtime          = file_exists($lockFile) ? filemtime($lockFile) : 0;
      $age            = $mtime ? (time() - $mtime) : PHP_INT_MAX;
      if ($age > $staleThreshold) {
        _log_shared(basename($options['file'] ?? 'CLI'), "Found stale lock $lockFile (age={$age}s). Removing.");
        @unlink($lockFile);
      } else {
        _log_shared(basename($options['file'] ?? 'CLI'), date(DATE_RFC3339) . " another process still running ({$lockFile} is locked)");
        exit;
      }
    }

    $lockInfo = json_encode(['pid' => getmypid(), 'ts' => date(DATE_RFC3339)]);
    write_file($lockFile, $lockInfo);
    Scheduler::register(function () use ($lockFile) {
      @delete_path($lockFile);
    }, 'release-cli-lock');
  }

  $file         = isset($options['f']) ? $options['f'] : (isset($options['file']) ? $options['file'] : null);
  $hashFilename = $file ? basename($file, '.txt') : (isset($options['file']) ? basename($options['file'], '.txt') : null);
  if ($hashFilename == '.txt' || empty($hashFilename)) {
    $hashFilename = basename(__FILE__, '.php') . '/cli';
  }

  $proxy = isset($options['p']) ? $options['p'] : (isset($options['proxy']) ? $options['proxy'] : null);

  if (!$file && !$proxy) {
    $proxy = load_proxies_for_mode($file, $proxy, 'http', $proxy_db);
  } elseif ($file) {
    $read = read_file($file);
    if ($read) {
      $proxy = $read;
    }
  }
}

// Ensure hash filename for logs
$hashFilename = $hashFilename ?? (basename(__FILE__, '.php') . '/manual');

// Lock management and file lock helper
$lockFolder = unixPath(tmp() . '/locks/');
$lockFiles  = glob($lockFolder . '/' . basename(__FILE__, '.php') . '*.lock');
if (count($lockFiles) > 2) {
  _log_shared($hashFilename ?? 'CLI', 'Anonymity checker process limit reached. Terminating process.');
  exit;
}

use PhpProxyHunter\FileLockHelper;

$runAllowed   = false;
$lockFilePath = isset($options['lockFile']) ? $options['lockFile'] : unixPath(tmp() . '/locks/' . $hashFilename . '.lock');
$fileLock     = new FileLockHelper($lockFilePath);
if ($fileLock->lock()) {
  $runAllowed = true;
  if (isset($proxy) && !empty($proxy)) {
    truncateFile(get_log_file_shared($hashFilename ?? 'CLI'));
    // proceed to checks
  }
} else {
  _log_shared($hashFilename ?? 'CLI', 'Another process is still running');
  exit;
}

// Function to run anonymity checks for one or many proxies
function run_anonymity_checks($proxyInput, $hashFilename, $proxy_db, $levels) {
  if (empty($proxyInput)) {
    _log_shared($hashFilename ?? 'CLI', 'No proxy input provided');
    return;
  }

  $proxies = extractProxies($proxyInput, $proxy_db, false);
  if (empty($proxies)) {
    _log_shared($hashFilename ?? 'CLI', 'No proxies found to check.');
    return;
  }

  foreach ($proxies as $pObj) {
    $proxyStr = $pObj->proxy;
    _log_shared($hashFilename ?? 'CLI', "Checking $proxyStr");

    $protocols   = ['http', 'socks4a', 'socks5h', 'socks4', 'socks5'];
    $perProtocol = [];
    $bestLevel   = 0;
    foreach ($protocols as $protocol) {
      $res = get_anonymity($proxyStr, $protocol, $pObj->username ?? null, $pObj->password ?? null);
      _log_shared($hashFilename ?? 'CLI', strtoupper($protocol) . ' => ' . ($res ?? 'null'));
      if (!$res) {
        continue;
      }
      $resLower               = strtolower($res);
      $perProtocol[$protocol] = $resLower;
      if (!isset($levels[$resLower])) {
        continue;
      }
      $lvl       = $levels[$resLower];
      $bestLevel = ($bestLevel === 0) ? $lvl : min($bestLevel, $lvl);
      if ($bestLevel === $levels['transparent']) {
        break;
      }
    }

    $final = null;
    if ($bestLevel > 0) {
      $final = array_search($bestLevel, $levels, true);
    } elseif (!empty($perProtocol)) {
      $final = reset($perProtocol);
    }

    if ($final !== null) {
      _log_shared($hashFilename ?? 'CLI', "Final Anonymity for $proxyStr: $final");
      $proxy_db->updateData($proxyStr, ['anonymity' => strtolower($final)]);
    } else {
      _log_shared($hashFilename ?? 'CLI', "Final Anonymity for $proxyStr: unknown");
    }
  }
}

if (isset($proxy) && !empty($proxy)) {
  run_anonymity_checks($proxy, $hashFilename ?? 'CLI', $proxy_db, $levels);
}

// Release lock
if (isset($fileLock) && $fileLock instanceof FileLockHelper) {
  $fileLock->unlock();
  _log_shared($hashFilename ?? 'CLI', 'Lock released');
}
