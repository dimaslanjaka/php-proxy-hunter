<?php

require __DIR__ . '/func-proxy.php';
global $isCli;

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

if (!$isCli) exit("only CLI allowed");

$lockFile = __DIR__ . '/proxyChecker.lock';
$statusFile = __DIR__ . "/status.txt";

$db = new ProxyDB();

$short_opts = "p:m::";
$long_opts = ["proxy::max::"];
$options = getopt($short_opts, $long_opts);

$str = implode("\n", array_values($options));
$proxies = extractProxies($str);
if (empty($proxies)) {
  $db_data = $db->getUntestedProxies(100);
  if (count($db_data) < 100) $db_data = $db->getDeadProxies(100);
  $db_data = array_merge($db_data, $db->getWorkingProxies(100));
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
  $proxies = array_filter($db_data_map, function ($item) use ($db) {
    if (!isValidProxy($item->proxy)) {
      if (!empty($item->proxy)) $db->remove($item->proxy);
      return false;
    }
    if (empty($item->last_check)) return true;
    if (isDateRFC3339OlderThanHours($item->last_check, 24)) return true;
    return false;
  });
}

for ($i = 0; $i < rand(1, 4); $i++) {
    shuffle($proxies);
}

$iterator = new ArrayIterator($proxies);
$combinedIterable = new MultipleIterator(MultipleIterator::MIT_NEED_ALL);
$combinedIterable->attachIterator($iterator);
$counter = 0;
$output_log = __DIR__ . '/proxyChecker.txt';
foreach ($combinedIterable as $index => $item) {
  $run_file = __DIR__ . '/tmp/runners/' . md5($item[0]->proxy) . '.txt';
  if (file_exists($run_file)) continue;
  // write lock
  write_file($run_file, '');
  write_file($statusFile, 'running in parallel');
  write_file($lockFile, 'running in parallel');
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
    // Record the start time
    $startTime = microtime(true);
    $running = null;
    do {
      curl_multi_exec($mh, $running);
      // Wait a short time before continuing to avoid consuming too much CPU
      curl_multi_select($mh);
    } while ($running > 0);
    // Record the end time
    $endTime = microtime(true);

    // Calculate the total latency
    $latency = round(($endTime - $startTime) * 1000);
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
          'private' => $isPrivate ? 'true' : 'false',
          'latency' => $latency
      ];
      $db->updateData($item[0]->proxy, $data);
      if (empty($item[0]->timezone) || empty($item[0]->country) || empty($item[0]->lang)) {
        foreach ($protocols as $protocol) {
          get_geo_ip($item[0]->proxy, $protocol, $db);
        }
      }
      if (empty($item[0]->useragent)) {
        $item[0]->useragent = randomWindowsUa();
        $db->updateData($item[0]->proxy, ['useragent' => $item[0]->useragent]);
      }
      if (empty($item[0]->webgl_renderer) || empty($item[0]->browser_vendor) || empty($item[0]->webgl_vendor)) {
        $webgl = random_webgl_data();
        $db->updateData($item[0]->proxy, [
            'webgl_renderer' => $webgl->webgl_renderer,
            'webgl_vendor' => $webgl->webgl_vendor,
            'browser_vendor' => $webgl->browser_vendor
        ]);
      }
      // write working proxies
      write_working();
    } else {
      $db->updateStatus($item[0]->proxy, 'dead');
      echo "$counter. {$item[0]->proxy} dead" . PHP_EOL;
      append_content_with_lock($output_log, "$counter. {$item[0]->proxy} dead\n");
    }
  }
  // release current proxy thread lock
  delete_path($run_file);
}

// write working proxies
write_working();

// release main lock files
delete_path($lockFile);
write_file($statusFile, 'idle');

function write_working() {
  global $db;
  echo "writing working proxies" . PHP_EOL;
  $data = parse_working_proxies($db);
  file_put_contents(__DIR__ . '/working.txt', $data['txt']);
  file_put_contents(__DIR__ . '/working.json', json_encode($data['array']));
  file_put_contents(__DIR__ . '/status.json', json_encode($data['counter']));
}
