<?php

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
  $ssl = 0
) {
  $ch = curl_init();
  // URL to test connectivity
  curl_setopt($ch, CURLOPT_URL, $endpoint);

  $default_headers = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.5',
    'Referer: https://www.google.com/',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0',
  ];

  $headers = array_merge($default_headers, $headers);

  // Make headers unique by header name
  $unique = [];
  foreach ($headers as $h) {
    [$key, $value] = explode(':', $h, 2);
    $key           = trim($key);
    $value         = trim($value);
    $unique[$key]  = $key . ': ' . $value;
  }

  $headers = array_values($unique);

  // Remove Accept-Encoding header
  $pattern = '/^(?:accept-?encoding:|Accept-?Encoding:).*/i';
  $headers = preg_grep($pattern, $headers, PREG_GREP_INVERT);

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
    curl_setopt($ch, CURLOPT_CAINFO, realpath(__DIR__ . '/../data/cacert.pem'));
    if (!empty($proxy)) {
      if (defined('CURLOPT_PROXY_SSL_VERIFYPEER')) {
        curl_setopt($ch, CURLOPT_PROXY_SSL_VERIFYPEER, 0);
      }
      if (defined('CURLOPT_PROXY_SSL_VERIFYHOST')) {
        curl_setopt($ch, CURLOPT_PROXY_SSL_VERIFYHOST, 0);
      }
    }
  }

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  // Set maximum connection time
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  // Set maximum response time
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
