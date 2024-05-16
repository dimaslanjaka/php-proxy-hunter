<?php

require __DIR__ . '/func-proxy.php';
global $isCli;

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

if (!$isCli) exit("only CLI allowed");

$db = new ProxyDB();

$short_opts = "p:";
$long_opts = ["proxy::"];
$options = getopt($short_opts, $long_opts);

$str = implode("\n", array_values($options));
$proxies = extractProxies($str);
if (empty($proxies)) {
  $db_untested = $db->getUntestedProxies(11);
  $db_data_map = array_map(function ($item) {
    $wrap = new Proxy($item['proxy']);
    foreach ($item as $key => $value) {
      if (property_exists($wrap, $key)) {
        $wrap->$key = $value;
      }
    }
    if (!empty($item['username']) && !empty($item['password'])) {
      $wrap->username = $item['username'];
      $wrap->password = $item['password'];
    }
    return $wrap;
  }, $db_untested);
  $proxies = $db_data_map;
}

shuffle($proxies);

$iterator = new ArrayIterator($proxies);
$combinedIterable = new MultipleIterator(MultipleIterator::MIT_NEED_ALL);
$combinedIterable->attachIterator($iterator);
foreach ($combinedIterable as $index => $item) {
  if (!isPortOpen($item[0]->proxy)) {
    $db->updateStatus($item[0]->proxy, 'port-closed');
    echo $item[0]->proxy . ' port closed' . PHP_EOL;
  } else {
    $ch = [
        buildCurl($item[0]->proxy, 'http', 'https://example.net', [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0'
        ], $item[0]->username, $item[0]->password),
        buildCurl($item[0]->proxy, 'socks4', 'https://example.net', [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0'
        ], $item[0]->username, $item[0]->password),
        buildCurl($item[0]->proxy, 'socks5', 'https://example.net', [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0'
        ], $item[0]->username, $item[0]->password)
    ];

    $mh = curl_multi_init();
    foreach ($ch as $handle) {
      if (is_resource($ch))
        curl_multi_add_handle($mh, $handle);
    }
    $running = null;
    do {
      curl_multi_exec($mh, $running);
    } while ($running > 0);
    $protocols = [];
    $isPrivate = false;
    foreach ($ch as $handle_index => $handle) {
      $http_status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
      $http_status_valid = $http_status == 200 || $http_status == 201 || $http_status == 202 || $http_status == 204 ||
          $http_status == 301 || $http_status == 302 || $http_status == 304;
      $protocol = $handle_index === 0 ? 'http' : ($handle_index === 1 ? 'socks4' : ($handle_index === 2 ? 'socks5' : ''));
      if ($http_status_valid) {
        $info = curl_getinfo($handle);
        $response = curl_multi_getcontent($handle);
        $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $response_header = substr($response, 0, $header_size);
        // is private proxy?
        $isPrivate = stripos($response_header, 'X-Forwarded-For:') !== false || stripos($response_header, 'Proxy-Authorization:') !== false;

        if (curl_errno($handle) || $response === false) {
          $error_msg = curl_error($handle);
          if (preg_match('/no authentication method was acceptable/mi', $error_msg)) {
            $isPrivate = true;
          }
        } else {
          // check proxy private by redirected to gateway url
          if (!$isPrivate) {
            $finalUrl = $info['url'];
            $pattern = '/^https?:\/\/(www\.gstatic\.com|gateway\.(zs\w+)\.net\/.*(origurl)=)/i';
            $isPrivate = preg_match($pattern, $finalUrl) !== false;
          }
        }
        echo "$protocol://{$item[0]->proxy} is working\n";
        $protocols[] = $protocol;
      }
    }

    // close
    foreach ($ch as $handle) {
      curl_multi_remove_handle($mh, $handle);
      curl_close($handle);
    }
    curl_multi_close($mh);

    if (!empty($protocols)) {
      $data = [
          'type' => implode('-', $protocols),
          'status' => 'active',
          'private' => $isPrivate ? 'true' : 'false'
      ];
      $db->updateData($item[0]->proxy, $data);
      foreach ($protocols as $protocol)
        get_geo_ip($item[0]->proxy, $protocol, $db);
    } else {
      $db->updateStatus($item[0]->proxy, 'dead');
      echo $item[0]->proxy . ' dead' . PHP_EOL;
    }
  }
}

echo "writing working proxies" . PHP_EOL;
$data = parse_working_proxies($db);
file_put_contents(__DIR__ . '/working.txt', $data['txt']);
file_put_contents(__DIR__ . '/working.json', json_encode($data['array']));
file_put_contents(__DIR__ . '/status.json', json_encode($data['counter']));
