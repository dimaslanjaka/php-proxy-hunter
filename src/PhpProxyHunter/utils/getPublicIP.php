<?php

/**
 * Retrieve the public IP address using multiple external services, with optional proxy support and simple file caching.
 *
 * @param bool  $cache       Enable or disable caching of the public IP result.
 * @param int   $cacheTimeout Cache timeout in seconds.
 * @param array $proxyInfo   Optional proxy information:
 *                           [
 *                             'proxy'    => string Proxy address,
 *                             'type'     => string Proxy type (http, socks5, etc),
 *                             'username' => string|null Proxy username,
 *                             'password' => string|null Proxy password
 *                           ]
 * @param bool  $nonSsl      If true, use only non-SSL (http) services.
 * @param bool  $debug       If true, enable debug output messages.
 *
 * @return string The detected public IP address, or an empty string if not found.
 */
function getPublicIP($cache = false, $cacheTimeout = 300, $proxyInfo = [], $nonSsl = false, $debug = false) {
  $ipServices = [
    'https://api64.ipify.org',
    'https://ipinfo.io/ip',
    'https://api.myip.com',
    'https://ip.42.pl/raw',
    'https://ifconfig.me/ip',
    'https://cloudflare.com/cdn-cgi/trace',
    'https://httpbin.org/ip',
    'https://api.ipify.org',
  ];
  if ($nonSsl) {
    // use non-SSL services only
    $ipServices = [
      'http://api64.ipify.org',
      'http://ipinfo.io/ip',
      'http://api.myip.com',
      'http://ip.42.pl/raw',
      'http://ifconfig.me/ip',
      'http://httpbin.org/ip',
      'http://api.ipify.org',
    ];
  }

  $cacheDir = tmp() . '/runners/public-ip';
  if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
  }

  $cacheKey = ($proxyInfo['proxy'] ?? '') !== ''
    ? md5(($proxyInfo['proxy'] ?? '') . ($proxyInfo['type'] ?? '') . ($proxyInfo['username'] ?? '') . ($proxyInfo['password'] ?? ''))
    : '';
  $cacheFile = $cacheDir . '/' . $cacheKey . '.cache';

  if ($cache && $cacheKey !== '') {
    if (file_exists($cacheFile)) {
      $data = @json_decode(@file_get_contents($cacheFile), true);
      if (is_array($data) && isset($data['ip'], $data['expires']) && $data['expires'] > time()) {
        return (string)$data['ip'];
      }
    }
  }

  $response = null;

  $proxyTypes = ['http', 'socks4', 'socks5', 'socks4a', 'socks5h'];
  $useProxy   = !empty($proxyInfo['proxy']);
  if ($useProxy) {
    if (!empty($proxyInfo['type'])) {
      $proxyTypes = [$proxyInfo['type']];
    } elseif (isset($proxyInfo['type']) && !in_array(strtolower($proxyInfo['type']), $proxyTypes, true)) {
      $proxyInfo['type'] = 'http';
    }
  }

  foreach ($ipServices as $idx => $url) {
    if ($useProxy) {
      // Rotate through proxy types if multiple are available
      foreach ($proxyTypes as $type) {
        if ($debug) {
          echo "Trying to get public IP from $url using proxy {$proxyInfo['proxy']} of type $type" . PHP_EOL;
        }
        $proxyInfo['type'] = $type;
        $ch                = buildCurl(
          $proxyInfo['proxy'] ?? null,
          $proxyInfo['type'] ?? null,
          $url,
          ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0'],
          $proxyInfo['username'] ?? null,
          $proxyInfo['password'] ?? null
        );

        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $output   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($debug) {
          echo "cURL response code: $httpCode" . PHP_EOL;
        }

        curl_close($ch);

        if ($output !== false) {
          if ($debug) {
            echo "Received response (type: $type): " . substr($output, 0, 100) . (strlen($output) > 100 ? '...' : '') . PHP_EOL;
          }
          $response = $output;
          break 2; // Break from both the proxy types loop and the services loop
        }
      }
    } else {
      if ($debug) {
        echo "Trying to get public IP from $url without proxy" . PHP_EOL;
      }
      $ch = buildCurl(
        null,
        null,
        $url,
        ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0']
      );

      curl_setopt($ch, CURLOPT_TIMEOUT, 5);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      $output   = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      curl_close($ch);

      if ($output !== false) {
        if ($debug) {
          echo 'Received response without proxy: ' . substr($output, 0, 100) . (strlen($output) > 100 ? '...' : '') . PHP_EOL;
        }
        $response = $output;
        break;
      }
    }
  }

  if (!$response) {
    return '';
  }

  // Parse IP using regex
  if (preg_match(
    "/(?!0)(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/",
    $response,
    $matches
  )) {
    $result = $matches[0] ?? '';
    if ($result !== '') {
      if ($cache && $cacheKey !== '') {
        $data = [
          'ip'      => $result,
          'expires' => time() + $cacheTimeout,
        ];
        @file_put_contents($cacheFile, json_encode($data));
      }
      return (string)$result;
    }
  }

  return '';
}
