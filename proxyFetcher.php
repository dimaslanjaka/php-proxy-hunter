<?php

// Array of URLs to fetch content from
$urls = array_unique([
  "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks5.txt",
  "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt",
  "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks4.txt",
  "https://raw.githubusercontent.com/TheSpeedX/PROXY-List/master/http.txt",
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
  "https://cyber-hub.pw/statics/proxy.txt"
]);

// File to append the content
$outputFile = __DIR__ . "/proxies.txt";

// Loop through each URL
foreach ($urls as $url) {
  // Fetch content from URL
  $content = file_get_contents($url);
  $ipPortList = [];

  // Match IP:PORT pattern using regular expression
  preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+)/', $content, $matches);

  // Add matched IP:PORT combinations to the list
  foreach ($matches[0] as $match) {
    $ipPortList[] = trim($match);
  }

  // Append content to output file
  file_put_contents($outputFile, "\n" . join(PHP_EOL, $ipPortList), FILE_APPEND | LOCK_EX);
}

echo "Content appended to $outputFile";
