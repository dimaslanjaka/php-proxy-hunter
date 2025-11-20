<?php

/** @noinspection PhpVariableIsUsedOnlyInClosureInspection */

// index all proxies into database

require_once __DIR__ . '/../func-proxy.php';
require_once __DIR__ . '/../php_backend/shared.php';

global $isWin, $isCli, $proxy_db;

use PhpProxyHunter\Scheduler;

if (!$isCli) {
  header('Content-Type:text/plain; charset=UTF-8');
  exit('web server access disallowed');
}

$lockFilePath = tmp() . '/proxies-all.lock';
$statusFile   = __DIR__ . '/../status.txt';

$isAdmin          = false;
$maxExecutionTime = 10 * 60;
// disable execution limit
set_time_limit(0);

if ($isCli) {
  $short_opts = 'p:m::';
  $long_opts  = [
    'proxy:',
    'max::',
    'userId::',
    'lockFile::',
    'runner::',
    'admin::',
  ];
  $options = getopt($short_opts, $long_opts);
  if (!empty($options['max'])) {
    $max = intval($options['max']);
    if ($max > 0) {
      $max_checks = $max;
    }
  }
  if (!empty($options['admin']) && $options['admin'] !== 'false') {
    $isAdmin = true;
    // set time limit 30 minutes for admin
    $maxExecutionTime = 30 * 60;
  }
}

if (file_exists($lockFilePath) && !is_debug() && !$isAdmin) {
  echo date(DATE_RFC3339) . ' another process still running' . PHP_EOL;
  // wait 30s before restart script
  sleep(30);
  restart_script();
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'indexing proxies');
}

Scheduler::register(function () use ($lockFilePath, $statusFile) {
  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
  file_put_contents($statusFile, 'idle');
}, 'z_onExit' . basename(__FILE__));

$db = $proxy_db;

$files  = [__DIR__ . '/../dead.txt', __DIR__ . '/../proxies.txt', __DIR__ . '/../proxies-all.txt'];
$assets = array_filter(getFilesByExtension(__DIR__ . '/../assets/proxies'), function ($fn) {
  return strpos($fn, 'added-') !== false;
});
if (!empty($assets)) {
  $files = array_filter(array_merge($files, $assets), 'file_exists');
}
$files = array_filter($files, 'is_file');
$files = array_map('realpath', $files);

$str_to_remove       = [];
$str_limit_to_remove = 10000;
$files_to_merge      = [];

foreach ($files as $file) {
  if (!file_exists($file)) {
    continue;
  }
  if (filterIpPortLines($file) == 'success') {
    echo 'non IP:PORT lines removed from ' . basename($file) . PHP_EOL;
  }
  $read        = read_file($file);
  $isFileEmpty = (is_string($read) && empty(trim($read))) || filesize($file) == 0;
  // Check if file is empty
  if ($isFileEmpty) {
    // Delete the file
    unlink($file);
    echo 'Deleted empty file: ' . basename($file) . PHP_EOL;
  } elseif (filesize($file) < 30000) {
    // merge and delete if the file is small (under 30kb)
    $files_to_merge[] = $file;
  }
}

if (!empty($files_to_merge)) {
  // merge and delete if the file is small (under 30kb)
  $contents = array_map(function ($file) {
    if (file_exists($file)) {
      return read_file($file);
    }
    return '';
  }, $files_to_merge);
  $contents = array_filter($contents, function ($content) {
    return !empty($content);
  });
  $content          = implode(PHP_EOL, $contents);
  $directory        = __DIR__ . '/../assets/proxies';
  $mergedFileName   = $directory . '/added-' . date('Ymd') . '_merged_file.txt';
  $mergedFileHandle = fopen($mergedFileName, 'w+');
  $write            = fwrite($mergedFileHandle, $content);
  fclose($mergedFileHandle);
  if ($write) {
    array_map(function ($file) {
      if (file_exists($file)) {
        unlink($file);
      }
    }, $files_to_merge);
    echo 'small files merged into ' . basename($mergedFileName) . PHP_EOL;
  }
}

$startTime = microtime(true);

iterateBigFilesLineByLine($files, 500, function ($line) use ($db, $str_limit_to_remove, &$str_to_remove, $startTime, $maxExecutionTime) {
  $items = extractProxies($line, $db, false);
  foreach ($items as $item) {
    if (empty($item->proxy) || $db->isAlreadyAdded($item->proxy)) {
      continue;
    }
    // Check if execution time has exceeded the maximum allowed time
    $elapsedTime = microtime(true) - $startTime;
    if ($elapsedTime > $maxExecutionTime) {
      // Execution time exceeded
      echo "Execution time exceeded maximum allowed time of {$maxExecutionTime} seconds." . PHP_EOL;
      exit(0);
    }
    if (!isValidProxy($item->proxy)) {
      if (count($str_to_remove) < $str_limit_to_remove) {
        $str_to_remove[] = $item->proxy;
      }
      echo $item->proxy . ' invalid' . PHP_EOL;
      continue;
    }
    $sel = $db->select($item->proxy);
    if (empty($sel)) {
      echo 'add ' . $item->proxy . PHP_EOL;
      // add proxy
      $db->add($item->proxy);
      // re-select proxy
      $sel = $db->select($item->proxy);
    }
    if (empty($sel[0]['status'])) {
      echo 'treat as untested ' . $item->proxy . PHP_EOL;
      $db->updateStatus($item->proxy, 'untested');
    }
    // re-check if proxy already indexed
    if (!empty($db->select($item->proxy))) {
      if (count($str_to_remove) < $str_limit_to_remove) {
        $str_to_remove[] = $sel[0]['proxy'];
      }
    }
    // mark proxy as added
    $db->markAsAdded($item->proxy);
  }
});

$indicator_all           = tmp() . '/proxies-all-should-iterating-database.txt';
$indicator_all_not_found = !file_exists($indicator_all);
$indicator_all_expired   = isFileCreatedMoreThanHours($indicator_all, 24);
$can_do_iterate          = $indicator_all_not_found || $indicator_all_expired;

if ($can_do_iterate) {
  echo 'iterating all proxies' . PHP_EOL;
  $db->iterateAllProxies(function ($item) use ($db, $str_limit_to_remove, &$str_to_remove) {
    if (!empty($item['proxy'])) {
      if (!isValidProxy($item['proxy'])) {
        // remove invalid proxy from database
        echo '[SQLite] remove invalid proxy (' . $item['proxy'] . ')' . PHP_EOL;
        $db->remove($item['proxy']);
      } else {
        // push indexed proxies to be removed from files
        if (count($str_to_remove) < $str_limit_to_remove) {
          $str_to_remove[] = $item['proxy'];
        }
      }
    }
  });
  if (!$indicator_all_not_found) {
    unlink($indicator_all);
  }
  append_content_with_lock($indicator_all, date(DATE_RFC3339));
}

blacklist_remover($db->db->pdo);

if (!empty($str_to_remove)) {
  foreach ($files as $file) {
    Scheduler::register(function () use (&$str_to_remove, $file) {
      $remove = removeStringFromFile($file, $str_to_remove);
      if ($remove == 'success') {
        echo '[FILE] removed indexed proxies from ' . basename($file) . ' (' . count($str_to_remove) . ')' . PHP_EOL;
      } else {
        echo $remove . PHP_EOL;
      }
    }, '[FILE] remove indexed ' . $file);
  }
} else {
  echo 'No proxies to remove' . PHP_EOL;
}

function countFilesAndRepeatScriptIfNeeded() {
  $directory = __DIR__ . '/../assets/proxies';
  $fileCount = 0;

  // Open the directory
  if ($handle = opendir($directory)) {
    // Count the files in the directory
    while (false !== ($entry = readdir($handle))) {
      if ($entry != '.' && $entry != '..') {
        $fileCount++;
      }
    }
    closedir($handle);
  }

  // Check if the file count is greater than 5
  if ($fileCount > 5) {
    restart_script();
    echo __FILE__ . " restarted\n";
  }

  echo "File count in directory '$directory': $fileCount\n";
}

function restart_script() {
  global $argv;
  $currentScript = __FILE__;
  $args          = implode(' ', array_slice($argv, 1));
  // Get all arguments except the script name

  // Execute the command
  runShellCommandLive("php $currentScript $args");
}

/**
 * Remove blacklisted IPs from the proxies database.
 *
 * This function reads a blacklist configuration file, extracts IPs from it,
 * and removes any proxy records from the "proxies" table that match those IPs,
 * except for rows whose status equals "active". The deletion is performed using
 * a parameterized DELETE statement to avoid SQL injection.
 *
 * Behavior:
 * - Supports PDO drivers "sqlite" and "mysql". If the PDO driver is not one of
 *   these, the function logs a notice and returns without performing deletions.
 * - The blacklist file path can be supplied via $blacklistConf; if not provided,
 *   the default path is "../data/blacklist.conf" relative to this file.
 * - The helper functions read_file() and extractIPs() are used to load and
 *   parse the blacklist contents.
 * - For each extracted IP, the function deletes rows where the "proxy" column
 *   contains the IP (using LIKE '%IP%') and the status is not "active".
 * - Each deletion is reported with the number of affected rows or the driver
 *   error information if the deletion fails.
 *
 * Notes:
 * - This function does not throw exceptions; database errors are reported via
 *   echo and the PDO statement errorInfo() output.
 *
 * @param \PDO        $pdo           PDO instance connected to the proxies database.
 * @param string|null $blacklistConf Optional path to a blacklist configuration file.
 *                                   Defaults to __DIR__ . '/../data/blacklist.conf'.
 * @return void
 *
 * @see read_file()
 * @see extractIPs()
 */
function blacklist_remover($pdo, $blacklistConf = null) {
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  if (!in_array($driver, ['sqlite', 'mysql'])) {
    echo "[BLACKLIST] Unsupported database driver: $driver. Skipping blacklist removal.\n";
    return;
  }
  $r_blacklist = read_file(!empty($blacklistConf) ? $blacklistConf : __DIR__ . '/../data/blacklist.conf');
  if ($r_blacklist) {
    $blacklist = extractIPs($r_blacklist);
    foreach ($blacklist as $ip) {
      // Prepare a parameterized query compatible with SQLite and MySQL
      // Use backticks for identifiers to be MySQL-friendly; SQLite accepts unquoted identifiers too.
      $query = 'DELETE FROM proxies WHERE proxy LIKE :proxy AND status != :active_status';
      $stmt  = $pdo->prepare($query);

      // Bind parameters
      $proxy        = "%$ip%";
      $activeStatus = 'active';
      $stmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
      $stmt->bindParam(':active_status', $activeStatus, PDO::PARAM_STR);

      // Execute the query and report affected rows
      if ($stmt->execute()) {
        $affectedRows = $stmt->rowCount();
        echo "[BLACKLIST] $ip deleted $affectedRows row(s).\n";
      } else {
        $err = $stmt->errorInfo();
        echo "[BLACKLIST] $ip failed to delete rows: " . json_encode($err) . "\n";
      }
    }
  }
}

// Scheduler::register('countFilesAndRepeatScriptIfNeeded', 'zz_restart_' . basename(__FILE__));
