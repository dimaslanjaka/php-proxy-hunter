<?php

/**
 * Filter open ports from proxy list.
 * Removes proxies with closed ports from working proxies and database.
 */

// Define project root for reuse
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/php_backend/shared.php';

use PhpProxyHunter\Proxy;

global $proxy_db;

$isCli = is_cli();

if (!$isCli) {
  header('Content-Type: text/plain; charset=UTF-8');
  exit('web server access disallowed');
}

$isAdmin = false;
// admin indicator
$max_checks = 500;
// max proxies to be checked
$maxExecutionTime = 120;
// max 120s execution time

if ($isCli) {
  $options = getopt('p:m::', [
    'proxy:',
    'max::',
    'userId::',
    'lockFile::',
    'runner::',
    'admin::',
  ]);

  if (!empty($options['max'])) {
    $m = intval($options['max']);
    if ($m > 0) {
      $max_checks = $m;
    }
  }

  if (!empty($options['admin']) && $options['admin'] !== 'false') {
    $isAdmin          = true;
    $maxExecutionTime = 600;
    set_time_limit(0);
  }
}

$hash         = sanitizeFilename(basename(__FILE__, '.php') . '-' . getUserId());
$lockFilePath = $projectRoot . '/tmp/locks/' . $hash . '.lock';
$statusFile   = $projectRoot . '/status.txt';

if (file_exists($lockFilePath) && !is_debug()) {
  exit(date(DATE_RFC3339) . " another process still running {$hash}" . PHP_EOL);
}

write_file($lockFilePath, date(DATE_RFC3339));
write_file($statusFile, 'filter-ports');

/**
 * Clean up lock file and reset status on script exit.
 *
 * @return void
 */
function filterPortsExitProcess() {
  global $lockFilePath, $statusFile;

  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
  write_file($statusFile, 'idle');
}

register_shutdown_function('filterPortsExitProcess');

$file = $projectRoot . '/proxies.txt';

// remove empty lines
removeEmptyLinesFromFile($file);

// Record the start time
$start_time = microtime(true);

try {
  // Fetch untested proxies from database with higher limit to reduce file I/O
  $db_data = $proxy_db->getUntestedProxies($max_checks);

  if (!empty($db_data)) {
    // Transform database records to Proxy instances
    foreach ($db_data as $item) {
      $proxy = new Proxy($item['proxy']);

      foreach ($item as $key => $value) {
        if (property_exists($proxy, $key)) {
          $proxy->$key = $value;
        }
      }

      if (!empty($item['username']) && !empty($item['password'])) {
        $proxy->username = $item['username'];
        $proxy->password = $item['password'];
      }

      processProxy($proxy->proxy);
    }
  } else {
    // Fall back to file-based proxies if no untested proxies in database
    $read    = read_first_lines($file, $max_checks) ?: [];
    $proxies = extractProxies(implode("\n", $read), null, false);

    if (!empty($proxies)) {
      shuffle($proxies);
      foreach ($proxies as $proxy) {
        processProxy($proxy->proxy);
      }
    }
  }
} catch (Exception $e) {
  echo 'fail extracting proxies ' . $e->getMessage() . PHP_EOL;
}

/**
 * Process a single proxy and check if its port is open.
 * Removes proxies with closed ports and updates database status.
 *
 * @param string $proxy The proxy address to check
 * @return void
 */
function processProxy($proxy) {
  global $start_time, $file, $proxy_db, $maxExecutionTime, $projectRoot;

  // Check if execution time exceeds [n] seconds
  if (microtime(true) - $start_time > $maxExecutionTime) {
    return;
  }

  if (!isPortOpen($proxy)) {
    removeStringAndMoveToFile($file, $projectRoot . '/dead.txt', $proxy);
    $proxy_db->updateData($proxy, ['status' => 'port-closed'], false);
    echo $proxy . ' port closed' . PHP_EOL;
  }
}
