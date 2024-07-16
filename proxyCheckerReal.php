<?php

require_once __DIR__ . '/func-proxy.php';

use PhpProxyHunter\ProxyDB;

// re-check working proxies
// real check whether actual title same

$max = 100;
$db = new ProxyDB(__DIR__ . '/src/database.sqlite');
$proxies = $db->getWorkingProxies($max);
if (count($proxies) < 10) {
  $proxies = array_merge($proxies, $db->getUntestedProxies($max));
}
if (count($proxies) < 10) {
  $proxies = array_merge($proxies, $db->getDeadProxies($max));
}

foreach ($proxies as $proxy) {
  realCheck($proxy['proxy']);
}

function getPageTitle($html)
{
  preg_match('/<title>(.*?)<\/title>/', $html, $matches);
  return isset($matches[1]) ? $matches[1] : null;
}

function realCheck($proxy)
{
  global $db;

  $headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
    'Accept-Language: en-US,en;q=0.9',
  ];

  /**
   * List of cURL handles for different proxy types
   * @var {\CurlHandle[]}
   */
  $chs = [
    'socks4' => buildCurl($proxy, 'socks4', 'https://bing.com', $headers),
    'http'   => buildCurl($proxy, 'http', 'https://bing.com', $headers),
    'socks5' => buildCurl($proxy, 'socks5', 'https://bing.com', $headers),
  ];

  // Initiate the multi-cURL handler
  $mh = curl_multi_init();

  // Add the handles to the multi-cURL handler
  foreach ($chs as $ch) {
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_multi_add_handle($mh, $ch);
  }

  $active = null;
  $protocols = [];

  // Execute the multi-cURL requests
  do {
    curl_multi_exec($mh, $active);
    curl_multi_select($mh);
  } while ($active > 0);

  // Retrieve and output the results for each proxy type
  foreach ($chs as $proxyType => $ch) {
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $html = curl_multi_getcontent($ch);
    $title = getPageTitle($html);

    // Example logic to determine proxy status
    if (strpos(strtolower($title), 'bing') !== false) {
      echo "$proxyType://$proxy working. Status code: $statusCode\n";
      $protocols[] = $proxyType;
    } else if (!empty($title)) {
      echo "$proxyType://$proxy dead. Status code: $statusCode. Title: $title\n";
    } else {
      echo "$proxyType://$proxy dead. Status code: $statusCode\n";
    }

    // Remove the handle from the multi-cURL handler
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
  }

  // Close the multi-cURL handler
  curl_multi_close($mh);

  if (!empty($protocols)) {
    $db->updateData($proxy, ['status' => 'active', 'type' => strtolower(implode('-', $protocols))]);
  } else {
    $db->updateStatus($proxy, 'dead');
  }
}
