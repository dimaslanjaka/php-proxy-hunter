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
$urls = [
  "https://proxies.lat/proxy.txt",
  "https://api.openproxylist.xyz/http.txt",
  "https://api.openproxylist.xyz/socks5.txt",
  "https://api.openproxylist.xyz/socks4.txt",
  "http://alexa.lr2b.com/proxylist.txt",
  "https://multiproxy.org/txt_all/proxy.txt",
  "https://multiproxy.org/txt_anon/proxy.txt",
  "https://proxyspace.pro/http.txt",
  "https://proxyspace.pro/https.txt",
  "http://rootjazz.com/proxies/proxies.txt",
  "https://api.proxyscrape.com/v3/free-proxy-list/get?request=displayproxies&proxy_format=protocolipport&format=text",
  "https://api.proxyscrape.com/v3/free-proxy-list/get?request=displayproxies&proxy_format=protocolipport&format=text&timeout=20000",
  "https://cyber-hub.pw/statics/proxy.txt",
  "https://github.com/ALIILAPRO/Proxy/raw/main/http.txt",
  "https://github.com/ALIILAPRO/Proxy/raw/main/socks4.txt",
  "https://github.com/ALIILAPRO/Proxy/raw/main/socks5.txt",
  "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/http_proxies.txt",
  "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/https_proxies.txt",
  "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/proxies_dump.json",
  "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/socks4_proxies.txt",
  "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/socks5_proxies.txt",
  "https://github.com/elliottophellia/yakumo/raw/master/results/mix_checked.txt",
  "https://github.com/officialputuid/KangProxy/raw/KangProxy/http/http.txt",
  "https://github.com/officialputuid/KangProxy/raw/KangProxy/https/https.txt",
  "https://github.com/officialputuid/KangProxy/raw/KangProxy/socks4/socks4.txt",
  "https://github.com/officialputuid/KangProxy/raw/KangProxy/socks5/socks5.txt",
  "https://github.com/prxchk/proxy-list/raw/main/all.txt",
  "https://github.com/proxifly/free-proxy-list/blob/main/proxies/all/data.txt",
  "https://github.com/roosterkid/openproxylist/blob/main/HTTPS_RAW.txt",
  "https://github.com/roosterkid/openproxylist/blob/main/SOCKS4_RAW.txt",
  "https://github.com/roosterkid/openproxylist/blob/main/SOCKS5_RAW.txt",
  "https://github.com/roosterkid/openproxylist/main/HTTPS_RAW.txt",
  "https://github.com/roosterkid/openproxylist/main/SOCKS4_RAW.txt",
  "https://github.com/roosterkid/openproxylist/main/SOCKS5_RAW.txt",
  "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt",
  "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks4.txt",
  "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks5.txt",
  "https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt",
  "https://raw.githubusercontent.com/hendrikbgr/Free-Proxy-Repo/master/proxy_list.txt",
  "https://raw.githubusercontent.com/jetkai/proxy-list/main/online-proxies/txt/proxies-http.txt",
  "https://raw.githubusercontent.com/mertguvencli/http-proxy-list/main/proxy-list/data.txt",
  "https://raw.githubusercontent.com/mmpx12/proxy-list/master/http.txt",
  "https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/http.txt",
  "https://raw.githubusercontent.com/proxy4parsing/proxy-list/main/http.txt",
  "https://raw.githubusercontent.com/RX4096/proxy-list/main/online/http.txt",
  "https://raw.githubusercontent.com/sunny9577/proxy-scraper/master/proxies.txt",
  "https://raw.githubusercontent.com/UptimerBot/proxy-list/main/proxies/http.txt",
  "https://raw.githubusercontent.com/rdavydov/proxy-list/main/proxies/http.txt",
  "https://spys.me/proxy.txt",
  "https://spys.me/socks.txt",
  "https://www.proxy-list.download/api/v1/get?type=http",
  "https://www.proxyscan.io/download?type=http",
  "https://yakumo.rei.my.id/ALL",
  "https://github.com/hookzof/socks5_list/raw/master/proxy.txt",
  "https://sunny9577.github.io/proxy-scraper/proxies.txt",
  "https://github.com/sunny9577/proxy-scraper/raw/master/proxies.txt",
  "https://raw.githubusercontent.com/B4RC0DE-TM/proxy-list/main/HTTP.txt",
  "https://raw.githubusercontent.com/RX4096/proxy-list/main/online/all.txt",
  "https://raw.githubusercontent.com/monosans/proxy-list/main/proxies_anonymous/http.txt",
  "https://raw.githubusercontent.com/shiftytr/proxy-list/master/proxy.txt",
  "https://raw.githubusercontent.com/TheSpeedX/PROXY-List/master/http.txt",
  "https://raw.githubusercontent.com/ShiftyTR/Proxy-List/master/http.txt",
  "https://raw.githubusercontent.com/BlackSnowDot/proxylist-update-every-minute/main/https.txt",
  "https://raw.githubusercontent.com/BlackSnowDot/proxylist-update-every-minute/main/http.txt",
  "https://raw.githubusercontent.com/opsxcq/proxy-list/master/list.txt",
  "https://raw.githubusercontent.com/UserR3X/proxy-list/main/online/https.txt"
];

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
