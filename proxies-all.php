<?php

/** @noinspection PhpVariableIsUsedOnlyInClosureInspection */

// index all proxies into database

require_once __DIR__ . "/func-proxy.php";

use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Scheduler;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  header('Content-Type:text/plain; charset=UTF-8');
}
if (!$isCli) {
  exit('web server access disallowed');
}

$lockFilePath = __DIR__ . "/tmp/proxies-all.lock";
$statusFile = __DIR__ . "/status.txt";

$isAdmin = false;
$maxExecutionTime = 10 * 60;
// disable execution limit
set_time_limit(0);

if ($isCli) {
  $short_opts = "p:m::";
  $long_opts = [
    "proxy:",
    "max::",
    "userId::",
    "lockFile::",
    "runner::",
    "admin::"
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

$db = new ProxyDB();

//$files = [__DIR__ . '/proxies.txt'];
$files = [__DIR__ . '/dead.txt', __DIR__ . '/proxies.txt', __DIR__ . '/proxies-all.txt'];
$assets = array_filter(getFilesByExtension(__DIR__ . '/assets/proxies'), function ($fn) {
  return strpos($fn, 'added-') !== false;
});
if (!empty($assets)) {
  $files = array_filter(array_merge($files, $assets), 'file_exists');
}
$files = array_filter($files, 'is_file');
$files = array_map('realpath', $files);

$str_to_remove = [];
$str_limit_to_remove = 10000;
$files_to_merge = [];

foreach ($files as $file) {
  if (!file_exists($file)) {
    continue;
  }
  if (filterIpPortLines($file) == 'success') {
    echo "non IP:PORT lines removed from " . basename($file) . PHP_EOL;
  }
  $read = read_file($file);
  $isFileEmpty = (is_string($read) && empty(trim($read))) || filesize($file) == 0;
  // Check if file is empty
  if ($isFileEmpty) {
    // Delete the file
    unlink($file);
    echo "Deleted empty file: " . basename($file) . PHP_EOL;
  } elseif (filesize($file) < 30000) {
    // merge and delete if the file is small (under 30kb)
    $files_to_merge[] = $file;
  }
}

if (!empty($files_to_merge)) {
  // merge and delete if the file is small (under 30kb)
  $contents = array_map(function (string $file) {
    if (file_exists($file)) {
      return read_file($file);
    }
    return '';
  }, $files_to_merge);
  $contents = array_filter($contents, function (string $content) {
    return !empty($content);
  });
  $content = implode(PHP_EOL, $contents);
  $directory = __DIR__ . '/assets/proxies';
  $mergedFileName = $directory . '/added-' . date("Ymd") . '_merged_file.txt';
  $mergedFileHandle = fopen($mergedFileName, 'w+');
  $write = fwrite($mergedFileHandle, $content);
  fclose($mergedFileHandle);
  if ($write) {
    array_map(function (string $file) {
      if (file_exists($file)) {
        unlink($file);
      }
    }, $files_to_merge);
    echo "small files merged into " . basename($mergedFileName) . PHP_EOL;
  }
}

$startTime = microtime(true);

iterateBigFilesLineByLine($files, function ($line) use ($db, $str_limit_to_remove, &$str_to_remove, $startTime, $maxExecutionTime) {
  $items = extractProxies($line, $db);
  foreach ($items as $item) {
    if (empty($item->proxy)) {
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
      echo "add " . $item->proxy . PHP_EOL;
      // add proxy
      $db->add($item->proxy);
      // re-select proxy
      $sel = $db->select($item->proxy);
    }
    if (empty($sel[0]['status'])) {
      echo "treat as untested " . $item->proxy . PHP_EOL;
      $db->updateStatus($item->proxy, 'untested');
    }
    // re-check if proxy already indexed
    if (!empty($db->select($item->proxy))) {
      if (count($str_to_remove) < $str_limit_to_remove) {
        $str_to_remove[] = $sel[0]['proxy'];
      }
    }
  }
});

$indicator_all = __DIR__ . '/tmp/proxies-all-should-iterating-database.txt';
$indicator_all_not_found = !file_exists($indicator_all);
$indicator_all_expired = isFileCreatedMoreThanHours($indicator_all, 24);
$can_do_iterate = $indicator_all_not_found || $indicator_all_expired;

if ($can_do_iterate) {
  echo "iterating all proxies" . PHP_EOL;
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

if (!empty($str_to_remove)) {
  foreach ($files as $file) {
    Scheduler::register(function () use (&$str_to_remove, $file) {
      $remove = removeStringFromFile($file, $str_to_remove);
      if ($remove == 'success') {
        echo "[FILE] removed indexed proxies from " . basename($file) . ' (' . count($str_to_remove) . ')' . PHP_EOL;
      } else {
        echo $remove . PHP_EOL;
      }
    }, "[FILE] remove indexed " . $file);
  }
} else {
  echo "No proxies to remove" . PHP_EOL;
}

function countFilesAndRepeatScriptIfNeeded()
{
  $directory = __DIR__ . '/assets/proxies';
  $fileCount = 0;

  // Open the directory
  if ($handle = opendir($directory)) {
    // Count the files in the directory
    while (false !== ($entry = readdir($handle))) {
      if ($entry != "." && $entry != "..") {
        $fileCount++;
      }
    }
    closedir($handle);
  }

  // Check if the file count is greater than 5
  if ($fileCount > 5) {
    restart_script();
  }

  echo "File count in directory '$directory': $fileCount\n";
}

function restart_script()
{
  // Get the current script path and arguments
  $currentScript = __FILE__;
  global $argv;
  $args = implode(' ', array_slice($argv, 1)); // Get all arguments except the script name

  // Determine the command based on the operating system
  if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Windows
    $command = "start /B php $currentScript $args > NUL 2>&1";
  } else {
    // Linux, Unix, MacOS
    $command = "php $currentScript $args > /dev/null 2>&1 &";
  }

  // Execute the command
  exec($command);

  // Exit the current script to avoid any further execution
  exit();
}

Scheduler::register('countFilesAndRepeatScriptIfNeeded', 'zz_repeat');
