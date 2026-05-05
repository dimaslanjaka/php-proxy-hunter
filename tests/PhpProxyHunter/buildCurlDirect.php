<?php

require_once __DIR__ . '/../bootstrap.php';

$deviceIp = getDeviceIp();
echo 'Device IP: ' . ($deviceIp ?? 'unknown') . "\n";

$input   = 'http://qtculbqe:iazrxzml7g27@31.59.20.176:6754';
$extract = extractProxies($input);

// endpoints useful for verifying proxy behavior
$endpoints = [
  'https://httpbin.org/ip',
  'https://api.ipify.org?format=json',
  'https://icanhazip.com',
  'https://checkip.amazonaws.com',
  'https://api.ipify.org?format=json',
];

for ($i = 0; $i < count($extract); $i++) {
  $data = $extract[$i];
  echo 'Proxy: ' . $data->proxy . "\n";
  if ($data->username) {
    echo 'Username: ' . $data->username . "\n";
  }
  if ($data->password) {
    echo 'Password: ' . $data->password . "\n";
  }

  foreach ($endpoints as $ep) {
    echo "==> Testing endpoint: $ep\n";
    try {
      $resp      = executeCurl($data->proxy, 'http', $ep, [], $data->username ?? null, $data->password ?? null, 'GET', null, 0, 10, 15);
      $http_code = $resp['curl_info']['http_code'] ?? null;
      echo 'Status Code: ' . ($http_code ?? 'unknown') . "\n";
      $body = $resp['result'] ?? '';
      if (is_string($body)) {
        $display = (strlen($body) <= 500) ? $body : substr($body, 0, 500) . '...';
        echo 'Content: ' . trim($display) . "\n";
      }

      // Extract first IPv4 from response
      $resp_ip = null;
      if (is_string($body) && preg_match('/(\d{1,3}(?:\.\d{1,3}){3})/', $body, $m)) {
        $resp_ip = $m[1];
      }

      if ($deviceIp && $resp_ip) {
        if ($resp_ip === $deviceIp) {
          echo "Validation: response IP matches device IP -> proxy NOT applied\n";
        } else {
          echo "Validation: response IP differs from device IP -> proxy applied\n";
        }
      } else {
        echo "Validation: could not extract IP from response or device IP unknown\n";
      }
    } catch (Throwable $e) {
      echo 'Request failed: ' . $e->getMessage() . "\n";
    }
    echo "\n";
  }
}
