<?php

require_once __DIR__ . '/func-proxy.php';

global $isCli;

if (!$isCli) {
  exit('Only CLI allowed');
}

// change config/CLI.json

$data = [
  "endpoint" => "https://www.example.com/",
  "headers" => [
    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
    "Accept-Language: en-US,en;q=0.5",
    "Connection: keep-alive",
    "Host: www.example.com",
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:91.0) Gecko/20100101 Firefox/91.0"
  ],
  "type" => "http|socks4|socks5"
];

write_file(__DIR__ . '/config/CLI.json', json_encode($data));
