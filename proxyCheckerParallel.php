<?php

require __DIR__ . '/func-proxy.php';
global $isCli;

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

if (!$isCli) exit("only CLI allowed");

$db = new ProxyDB();

$short_opts = "p:m::";
$long_opts = ["proxy::max::"];
$options = getopt($short_opts, $long_opts);

$str = implode("\n", array_values($options));
$proxies = extractProxies($str);
if (empty($proxies)) {
//  $db_untested = $db->getUntestedProxies(100);
//  $db_private = $db->getPrivateProxies(100);
//  $db_data = array_merge($db_untested, $db_private);
  $db_data = $db->getUntestedProxies(100);
  $db_data_map = array_map(function ($item) {
    // transform array into Proxy instance same as extractProxies result
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
  }, $db_data);
  $proxies = $db_data_map;
}

shuffle($proxies);

$iterator = new ArrayIterator($proxies);
$combinedIterable = new MultipleIterator(MultipleIterator::MIT_NEED_ALL);
$combinedIterable->attachIterator($iterator);
$counter = 0;
$output_log = __DIR__ . '/proxyChecker.txt';
foreach ($combinedIterable as $index => $item) {
  $run_file = __DIR__ . '/tmp/runners/' . md5($item[0]->proxy) . '.txt';
  if (file_exists($run_file)) continue;
  write_file($run_file, '');
  $counter++;
  if (!isPortOpen($item[0]->proxy)) {
    $db->updateStatus($item[0]->proxy, 'port-closed');
    echo "$counter. {$item[0]->proxy} port closed" . PHP_EOL;
    append_content_with_lock($output_log, "$counter. {$item[0]->proxy} port closed\n");
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

    $protocols = [];
    $mh = curl_multi_init();
    foreach ($ch as $handle_index => $handle) {
      $protocol = $handle_index === 0 ? 'http' : ($handle_index === 1 ? 'socks4' : ($handle_index === 2 ? 'socks5' : ''));
      $protocols[$handle_index] = $protocol;
      curl_multi_add_handle($mh, $handle);
    }
    $running = null;
    do {
      curl_multi_exec($mh, $running);
    } while ($running > 0);
    $isPrivate = false;
    $isWorking = false;
    foreach ($ch as $handle_index => $handle) {
      $http_status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
      $http_status_valid = $http_status == 200 || $http_status == 201 || $http_status == 202 || $http_status == 204 ||
          $http_status == 301 || $http_status == 302 || $http_status == 304;
      $protocol = $protocols[$handle_index];
      if ($http_status_valid) {
        $info = curl_getinfo($handle);
        $response = curl_multi_getcontent($handle);
        $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $response_header = substr($response, 0, $header_size);
        // is private proxy?
        $isPrivate = stripos($response_header, 'Proxy-Authorization:') !== false;

        if (curl_errno($handle) || $response === false) {
          $error_msg = curl_error($handle);
          if (preg_match('/no authentication method was acceptable/mi', $error_msg)) {
            $isPrivate = true;
//            echo "$protocol://{$item[0]->proxy} private " . $error_msg. PHP_EOL;
          }
        } else {
          // check proxy private by redirected to gateway url
          if (!$isPrivate) {
            $finalUrl = $info['url'];
            $pattern = '/^https?:\/\/(www\.gstatic\.com|gateway\.(zs\w+)\.net\/.*(origurl)=)/i';
            $isPrivate = preg_match($pattern, $finalUrl) > 0;
          }
        }
        echo "$counter. $protocol://{$item[0]->proxy} is working (private " . ($isPrivate ? 'true' : 'false') . ")\n";
        append_content_with_lock($output_log, "$counter. $protocol://{$item[0]->proxy} is working\n");
        $isWorking = true;
      }
    }

    // close
    foreach ($ch as $handle) {
      curl_multi_remove_handle($mh, $handle);
      curl_close($handle);
    }
    curl_multi_close($mh);

    if ($isWorking) {
      $data = [
          'type' => implode('-', $protocols),
          'status' => 'active',
          'private' => $isPrivate ? 'true' : 'false'
      ];
      $db->updateData($item[0]->proxy, $data);
      if (empty($item[0]->timezone) || empty($item[0]->country) || empty($item[0]->lang)) {
        foreach ($protocols as $protocol) {
          get_geo_ip($item[0]->proxy, $protocol, $db);
        }
      }
      if (empty($item[0]->webgl_renderer) || empty($item[0]->browser_vendor) || empty($item[0]->webgl_vendor)) {
        $webgl = random_webgl_data();
        $db->updateData($item[0]->proxy, [
            'webgl_renderer' => $webgl->webgl_renderer,
            'webgl_vendor' => $webgl->webgl_vendor,
            'browser_vendor' => $webgl->browser_vendor
        ]);
      }
    } else {
      $db->updateStatus($item[0]->proxy, 'dead');
      echo "$counter. {$item[0]->proxy} dead" . PHP_EOL;
      append_content_with_lock($output_log, "$counter. {$item[0]->proxy} dead\n");
    }
  }
  if (file_exists($run_file)) unlink($run_file);
}

echo "writing working proxies" . PHP_EOL;
$data = parse_working_proxies($db);
file_put_contents(__DIR__ . '/working.txt', $data['txt']);
file_put_contents(__DIR__ . '/working.json', json_encode($data['array']));
file_put_contents(__DIR__ . '/status.json', json_encode($data['counter']));
