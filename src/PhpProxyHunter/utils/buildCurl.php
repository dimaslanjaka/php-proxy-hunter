<?php

/**
 * Merge two arrays of HTTP headers while ensuring uniqueness based on the header name.
 *
 * This helper is tolerant: it accepts "Key: Value" or "Key:Value", trims
 * whitespace, uses case-insensitive header names where the last occurrence
 * wins, and by default removes `Accept-Encoding` (to avoid compressed bodies
 * when the caller does not want them).
 *
 * @param array $defaultHeaders
 * @param array $additionalHeaders
 * @param bool $removeAcceptEncoding
 * @return array
 */
function mergeHeaders($defaultHeaders, $additionalHeaders, $removeAcceptEncoding = true) {
  $finalMap = [];
  $all      = array_merge((array)$defaultHeaders, (array)$additionalHeaders);
  foreach ($all as $header) {
    if (!is_string($header)) {
      continue;
    }
    $header = trim($header);
    if ($header === '') {
      continue;
    }
    $parts = preg_split('/:\s*/', $header, 2);
    $key   = isset($parts[0]) ? trim($parts[0]) : '';
    $value = isset($parts[1]) ? trim($parts[1]) : '';
    if ($key === '') {
      continue;
    }
    $lower            = strtolower($key);
    $finalMap[$lower] = ['orig' => $key, 'value' => $value];
  }
  $result = [];
  foreach ($finalMap as $lower => $pair) {
    if ($removeAcceptEncoding && $lower === 'accept-encoding') {
      continue;
    }
    $result[] = $pair['orig'] . ': ' . $pair['value'];
  }
  return $result;
}

/**
 * Build a cURL handle for making HTTP requests.
 *
 * @param string|null $proxy Proxy address. Default is null.
 * @param string|null $type Type of proxy. Default is 'http'. Possible values are 'http', 'socks4', 'socks5', 'socks4a', 'socks5h', or null.
 *   - 'http': HTTP proxy
 *   - 'socks4': SOCKS4 proxy
 *   - 'socks4a': SOCKS4A proxy (hostname resolution via proxy)
 *   - 'socks5': SOCKS5 proxy (IP address resolution only)
 *   - 'socks5h': SOCKS5 proxy with remote DNS/hostname resolution (requires CURLPROXY_SOCKS5_HOSTNAME)
 * @param string $endpoint The URL to send the HTTP request to. Default is 'https://bing.com'.
 * @param string[] $headers An array of HTTP header strings to send with the request. Default is an empty array.
 * @param string|null $username Proxy authentication username. Default is null.
 * @param string|null $password Proxy authentication password. Default is null.
 * @param string $method HTTP method for the request. Default is 'GET'. Possible values are 'GET', 'POST', 'PUT', 'PATCH', 'DELETE'.
 * @param array|string|null $post_data Data to be sent in the request body for POST, PUT, PATCH requests. Default is null.
 * @param int $ssl SSL/TLS version to use. Default is 0 (auto-detect).
 *              - 0: Auto-detect highest available version.
 *              - 1: Force TLS v1.0.
 *              - 2: Force TLS v1.2.
 *              - 3: Force TLS v1.3.
 * @param int $connect_timeout Connection timeout in seconds. Default is 10.
 * @param int $timeout        Maximum response time in seconds. Default is 10.
 * @return \CurlHandle|\resource Returns a cURL handle on success, false on failure.
 */
function buildCurl(
  $proxy = null,
  $type = 'http',
  $endpoint = 'https://bing.com',
  $headers = [],
  $username = null,
  $password = null,
  $method = 'GET',
  $post_data = null,
  $ssl = 0,
  $connect_timeout = 10,
  $timeout = 10
) {
  $ch = curl_init();
  // URL to test connectivity
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  $default_headers = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.5',
    'Referer: https://www.google.com/search?q=' . urlencode($endpoint),
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0',
  ];

  // Use the shared helper to merge and deduplicate headers (last wins).
  $headers = mergeHeaders($default_headers, $headers);

  if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    // Proxy address
    if (!is_null($username) && !is_null($password)) {
      curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$username:$password");
      // Set proxy authentication credentials
    }
    // Determine the CURL proxy type based on the specified $type
    $proxy_type = CURLPROXY_HTTP;
    $type_lc    = strtolower($type);
    if ($type_lc === 'socks5h') {
      if (defined('CURLPROXY_SOCKS5_HOSTNAME')) {
        $proxy_type = CURLPROXY_SOCKS5_HOSTNAME;
      } else {
        // Fallback to CURLPROXY_SOCKS5 if CURLPROXY_SOCKS5_HOSTNAME is not defined
        $proxy_type = CURLPROXY_SOCKS5;
      }
      // set CURLOPT_PROXY with supported format
      $extractIPPort = extractProxies($proxy);
      if (!empty($extractIPPort)) {
        // Use the first extracted proxy
        $proxy = $extractIPPort[0]->proxy;
        curl_setopt($ch, CURLOPT_PROXY, "socks5h://$proxy");
      }
    } elseif ($type_lc === 'socks5') {
      $proxy_type = CURLPROXY_SOCKS5;
    } elseif ($type_lc === 'socks4') {
      $proxy_type = CURLPROXY_SOCKS4;
    } elseif ($type_lc === 'socks4a') {
      $proxy_type = CURLPROXY_SOCKS4A;
    }
    curl_setopt($ch, CURLOPT_PROXYTYPE, $proxy_type);
    // Specify proxy type
  }

  if (strpos($endpoint, 'https') !== false) {
    // curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1.2:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-SHA');
    if (defined('CURL_SSLVERSION_TLSv1_3') && $ssl === 3) {
      // Check for TLS 1.3 support first (if available)
      curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_3);
    // CURL_SSLVERSION_TLSv1_3 = 7
    } elseif (defined('CURL_SSLVERSION_TLSv1_2') && $ssl === 2) {
      // Check for TLS 1.2 support
      curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    } elseif (defined('CURL_SSLVERSION_TLSv1_0') && $ssl === 1) {
      // Check for TLS 1.0 support
      curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_0);
    // CURL_SSLVERSION_TLSv1_0 = 4
    } elseif (defined('CURL_SSLVERSION_MAX_DEFAULT')) {
      curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_MAX_DEFAULT);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, 0);
    curl_setopt($ch, CURLOPT_CAINFO, realpath(__DIR__ . '/cacert.pem'));
    if (!empty($proxy)) {
      if (defined('CURLOPT_PROXY_SSL_VERIFYPEER')) {
        curl_setopt($ch, CURLOPT_PROXY_SSL_VERIFYPEER, 0);
      }
      if (defined('CURLOPT_PROXY_SSL_VERIFYHOST')) {
        curl_setopt($ch, CURLOPT_PROXY_SSL_VERIFYHOST, 0);
      }
    }
  }

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $connect_timeout);
  // Set maximum connection time (seconds)
  curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeout);
  // Set maximum response time (seconds)
  // curl_setopt($ch, CURLOPT_VERBOSE, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  // curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

  $cookies = get_project_root() . '/tmp/cookies/default.txt';
  if (!is_dir(dirname($cookies))) {
    mkdir(dirname($cookies), 0777, true);
  }
  if (!file_exists($cookies)) {
    write_file($cookies, '');
  }
  curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
  // Save cookies to file
  curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
  // Use cookies from file

  // Set a random Android User-Agent if none is specified
  $userAgent = randomAndroidUa();
  foreach ($headers as $header) {
    if (preg_match('/^(?:user-agent|User-Agent):\s*(.*)$/i', $header, $matches)) {
      $userAgent = trim($matches[1]);
      break;
    }
  }
  curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  // Handle compressed response
  // curl_setopt($ch, CURLOPT_ENCODING, 'deflate, gzip, br');
  curl_setopt($ch, CURLOPT_ENCODING, '');

  // Set the request method and data if needed
  switch (strtoupper($method)) {
    case 'POST':
    case 'PUT':
    case 'PATCH':
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
      if (!empty($post_data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
      }
      break;
    case 'DELETE':
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
      break;
    default:
      curl_setopt($ch, CURLOPT_HTTPGET, true);
  }

  return $ch;
}

/**
 * Executes a cURL request using the configuration provided by buildCurl().
 *
 * @param string|null $proxy        Optional proxy address (host:port).
 * @param string      $type         Proxy type: e.g. "http", "socks4", "socks5".
 * @param string      $endpoint     Target URL to call.
 * @param array       $headers      Array of additional HTTP headers.
 * @param string|null $username     Optional username for authentication.
 * @param string|null $password     Optional password for authentication.
 * @param string      $method       HTTP method: GET, POST, PUT, DELETE, etc.
 * @param mixed       $post_data    Data to send with POST/PUT requests.
 * @param int         $ssl          SSL verification mode (0 = off, 1 = on).
 * @param int         $connect_timeout Connection timeout in seconds. Default is 10.
 * @param int         $timeout        Maximum response time in seconds. Default is 10.
 *
 * @return array{
 *     result: mixed,
 *     latency_s: float,
 *     latency_ms: float,
 *     curl_info: array
 * }
 *     Returns an array containing:
 *       - result:     The raw response body returned by curl_exec().
 *       - latency_s:  Total request latency in seconds.
 *       - latency_ms: Total request latency in milliseconds.
 *       - curl_info:  Data from curl_getinfo() describing the request.
 */
function executeCurl(
  $proxy = null,
  $type = 'http',
  $endpoint = 'https://bing.com',
  $headers = [],
  $username = null,
  $password = null,
  $method = 'GET',
  $post_data = null,
  $ssl = 0,
  $connect_timeout = 10,
  $timeout = 10
) {
  $ch = buildCurl($proxy, $type, $endpoint, $headers, $username, $password, $method, $post_data, $ssl, $connect_timeout, $timeout);

  $start = microtime(true);
  // measure latency (start)
  $result = curl_exec($ch);
  $end    = microtime(true);
  // measure latency (end)

  $latency = $end - $start;
  // final latency in seconds
  $latency_ms = round($latency * 1000, 2);
  // milliseconds

  $curl_info = curl_getinfo($ch);
  // capture curl info before closing
  curl_close($ch);

  return [
    'result'     => $result,
    'latency_s'  => $latency,
    'latency_ms' => $latency_ms,
    'curl_info'  => $curl_info,
  ];
}
