<?php

require_once __DIR__ . '/func-proxy.php';

use \PhpProxyHunter\Scheduler;

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');
if (!$isCli)
  exit('web server access disallowed');

if (function_exists('header')) header('Content-Type: text/plain; charset=UTF-8');

$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath) && !is_debug()) {
  echo "another process still running\n";
  exit();
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'fetching new proxies');
}

Scheduler::register(function () use ($lockFilePath, $statusFile, $db) {
  echo "releasing lock" . PHP_EOL;
  // clean lock files
  if (file_exists($lockFilePath))
    unlink($lockFilePath);
  echo "update status to IDLE" . PHP_EOL;
  file_put_contents($statusFile, 'idle');
}, 'z_onExit' . __FILE__);

// Array of URLs to fetch content from
$urls = array_unique([
    "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks5.txt",
    "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt",
    "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks4.txt",
    "https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt",
    "https://raw.githubusercontent.com/ShiftyTR/Proxy-List/master/http.txt",
    "https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/http.txt",
    "https://raw.githubusercontent.com/RX4096/proxy-list/main/online/http.txt",
    "https://raw.githubusercontent.com/jetkai/proxy-list/main/online-proxies/txt/proxies-http.txt",
    "https://raw.githubusercontent.com/mmpx12/proxy-list/master/http.txt",
    "https://raw.githubusercontent.com/sunny9577/proxy-scraper/master/proxies.txt",
    "https://raw.githubusercontent.com/proxy4parsing/proxy-list/main/http.txt",
    "https://raw.githubusercontent.com/roosterkid/openproxylist/main/HTTPS_RAW.txt",
    "https://raw.githubusercontent.com/mertguvencli/http-proxy-list/main/proxy-list/data.txt",
    "https://raw.githubusercontent.com/hendrikbgr/Free-Proxy-Repo/master/proxy_list.txt",
    "https://raw.githubusercontent.com/almroot/proxylist/master/list.txt",
    "https://www.proxy-list.download/api/v1/get?type=http",
    "https://www.proxyscan.io/download?type=http",
    "https://raw.githubusercontent.com/rdavydov/proxy-list/main/proxies/http.txt",
    "https://raw.githubusercontent.com/UptimerBot/proxy-list/main/proxies/http.txt",
    "https://api.openproxylist.xyz/http.txt",
    "https://cyber-hub.pw/statics/proxy.txt",
    "https://spys.me/proxy.txt",
    "https://spys.me/socks.txt",
    "https://proxylist.geonode.com/api/proxy-list?limit=500&page=1&sort_by=lastChecked&sort_type=desc",
    "https://api.proxyscrape.com/v3/free-proxy-list/get?request=displayproxies&proxy_format=protocolipport&format=text&timeout=20000",
    "https://api.proxyscrape.com/v3/free-proxy-list/get?request=displayproxies&proxy_format=protocolipport&format=text",
    "https://github.com/roosterkid/openproxylist/blob/main/HTTPS_RAW.txt",
    "https://github.com/roosterkid/openproxylist/blob/main/SOCKS4_RAW.txt",
    "https://github.com/roosterkid/openproxylist/blob/main/SOCKS5_RAW.txt",
    "https://github.com/proxifly/free-proxy-list/blob/main/proxies/all/data.txt" .
    "https://github.com/officialputuid/KangProxy/raw/KangProxy/http/http.txt",
    "https://github.com/officialputuid/KangProxy/raw/KangProxy/https/https.txt",
    "https://github.com/officialputuid/KangProxy/raw/KangProxy/socks4/socks4.txt",
    "https://github.com/officialputuid/KangProxy/raw/KangProxy/socks5/socks5.txt",
    "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/http_proxies.txt",
    "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/https_proxies.txt",
    "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/proxies_dump.json",
    "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/socks4_proxies.txt",
    "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/socks5_proxies.txt",
    "https://github.com/prxchk/proxy-list/raw/main/all.txt",
    "https://github.com/clarketm/proxy-list/raw/master/proxy-list.txt"
]);

// File to append the content
$outputFile = __DIR__ . "/proxies.txt";
$maxFileSize = 500 * 1024; // 500KB in bytes

// Check if the file exceeds the maximum size
if (file_exists($outputFile) && filesize($outputFile) > $maxFileSize) {
    // Generate a new file name
    $outputFile = __DIR__ . "/assets/proxies/added-" . date("Ymd_His") . ".txt";
}

// Loop through each URL
foreach ($urls as $url) {
  // Fetch content from URL
  $content = curlGetWithProxy($url, null, null, 3600);
  if (!$content) $content = '';
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
        removeDuplicateLines($outputFile);
      } else {
        echo $filter . PHP_EOL;
      }
    }
  }, "append content " . md5($url));
}

//echo "Content appended to $outputFile" . PHP_EOL;
