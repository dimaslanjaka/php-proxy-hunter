<?php

require_once __DIR__ . '/func-proxy.php';

use \PhpProxyHunter\Scheduler;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  header('Content-Type:text/plain; charset=UTF-8');
}
if (!$isCli) {
  exit('web server access disallowed');
}

if (function_exists('header')) {
  header('Content-Type: text/plain; charset=UTF-8');
}

$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath) && !is_debug()) {
  echo date(DATE_RFC3339) . ' another process still running' . PHP_EOL;
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'fetching new proxies');
}

Scheduler::register(function () use ($lockFilePath, $statusFile, $db) {
  echo "releasing lock" . PHP_EOL;
  // clean lock files
  if (file_exists($lockFilePath)) {
    unlink($lockFilePath);
  }
  echo "update status to IDLE" . PHP_EOL;
  file_put_contents($statusFile, 'idle');
}, 'z_onExit' . basename(__FILE__));

// Array of URLs to fetch content from
$urls = json_decode(read_file(__DIR__ . '/proxyFetcherSources.json'));

$urls = array_unique($urls);

// Split the $urls array into chunks of 5 items each
$chunks = array_chunk($urls, 5);

// Loop through each chunk with an index
foreach ($chunks as $index => $chunk) {
  // Create a unique filename for each chunk
  $outputFile = __DIR__ . "/assets/proxies/added-fetch-" . date("Ymd") . "-chunk-" . ($index + 1) . ".txt";

  foreach ($chunk as $url) {
    // Fetch content from URL
    $content = curlGetWithProxy($url, null, null, 3600);
    if (!$content) {
      $content = '';
    }
    $json = json_decode(trim($content), true);
    if (json_last_error() === JSON_ERROR_NONE) {
      $content = '';
      if (isset($json['data'])) {
        if (is_array($json['data'])) {
          foreach ($json['data'] as $item) {
            if (isset($item['ip']) && isset($item['port'])) {
              $proxy = trim($item['ip']) . ":" . trim($item['port']);
              $content .= $proxy . PHP_EOL;
            } else {
              var_dump($item);
            }
          }
        }
      } else {
        var_dump($json);
      }
    }

    // Append content to output file
    Scheduler::register(function () use ($outputFile, $content, $url) {
      $fallback_file = __DIR__ . '/assets/proxies/added-fetch-' . md5($url) . '.txt';
      $append = append_content_with_lock($outputFile, "\n" . $content . "\n");
      if (!$append) {
        $outputFile = $fallback_file;
        $append = append_content_with_lock($fallback_file, "\n" . $content . "\n");
      }
      if ($append) {
        $filter = filterIpPortLines($outputFile);
        if ($filter == 'success') {
          echo 'non proxy lines removed from ' . basename($outputFile) . PHP_EOL;
          sleep(1);
          removeDuplicateLines($outputFile);
          echo 'duplicate lines removed from ' . basename($outputFile) . PHP_EOL;
        } else {
          echo $filter . PHP_EOL;
        }
        sleep(1);
      }
    }, "append content " . md5($url));
  }
}
