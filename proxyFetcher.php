<?php

// Array of URLs to fetch content from
$urls = [
  "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks5.txt",
  "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt",
  "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks4.txt"
];

// File to append the content
$outputFile = __DIR__ . "/proxies.txt";

// Loop through each URL
foreach ($urls as $url) {
  // Fetch content from URL
  $content = file_get_contents($url);

  // Append content to output file
  file_put_contents($outputFile, "\n" . $content, FILE_APPEND | LOCK_EX);
}

echo "Content appended to $outputFile";
