<?php

require __DIR__ . '/../func-proxy.php';

$proxy = '184.168.124.233:5402';
$proxy = '3.140.243.225:1342';
$proxy = '72.10.160.172:1889';

// try access proxy directly
$ch = buildCurl(null, null, 'http://' . $proxy);
curl_setopt($ch, CURLINFO_HEADER_OUT, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$response_header = substr($response, 0, $header_size);
$body = substr($response, $header_size);
echo "DIRECT PROXY ACCESS\n\n";
echo trim($response_header) . PHP_EOL;
if (!empty($body)) {
  $dom = \simplehtmldom\helper::str_get_html($body);
  echo "title: " . $dom->title() . "\n\n";
}

echo "TEST PROXY CONNECTION\n\n";
$cek = checkProxy($proxy, 'http', 'https://bing.com');
$ch = $cek['curl'];
echo "RESULT: " . ($cek['result'] ? 'true' : 'false') . "\n\n";
if (!$cek['result']) {
  echo 'ERROR: ' . $cek['error'] . "\n\n";
}
echo trim($cek['response-headers']) . "\n\n";
$body = trim($cek['body']);
if (!empty($body)) {
  $dom = \simplehtmldom\helper::str_get_html($body);
  echo "title: " . $dom->title() . "\n\n";
}
