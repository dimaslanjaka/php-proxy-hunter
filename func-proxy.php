<?php

/** @noinspection RegExpRedundantEscape */
/** @noinspection RegExpUnnecessaryNonCapturingGroup */

require_once __DIR__ . '/func.php';

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

/**
 * Merge two arrays of HTTP headers while ensuring uniqueness based on the keys.
 *
 * @param array $defaultHeaders The array of default headers.
 * @param array $additionalHeaders The array of additional headers to merge.
 * @return array The merged array of headers with unique keys.
 */
function mergeHeaders(array $defaultHeaders, array $additionalHeaders): array
{
  // Convert the arrays into associative arrays with header keys as keys
  $convertToAssocArray = function ($headers) {
    $assocArray = [];
    foreach ($headers as $header) {
      $parts = explode(': ', $header, 2);
      $assocArray[$parts[0]] = $parts[1];
    }
    return $assocArray;
  };

  // Merge two associative arrays while overwriting duplicates
  $mergedHeaders = array_merge($convertToAssocArray($defaultHeaders), $convertToAssocArray($additionalHeaders));

  // Convert the merged associative array back into a sequential array
  $finalHeaders = [];
  foreach ($mergedHeaders as $key => $value) {
    $finalHeaders[] = "$key: $value";
  }

  return $finalHeaders;
}

/**
 * Build a cURL handle for making HTTP requests.
 *
 * @param string|null $proxy Proxy address. Default is null.
 * @param string|null $type Type of proxy. Default is 'http'. Possible values are 'http', 'socks4', 'socks5', 'socks4a', or null.
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
  curl_setopt($ch, CURLOPT_URL, $endpoint); // URL to test connectivity

  $default_headers = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.5',
    'Referer: https://www.google.com/',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0'
  ];

  $headers = array_merge($default_headers, $headers);

  // Remove Accept-Encoding header
  $pattern = '/^(?:accept-?encoding:|Accept-?Encoding:).*/i';
  $headers = preg_grep($pattern, $headers, PREG_GREP_INVERT);

  if (!empty($proxy)) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy); // Proxy address
    if (!is_null($username) && !is_null($password)) {
      curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$username:$password"); // Set proxy authentication credentials
    }
    // Determine the CURL proxy type based on the specified $type
    $proxy_type = CURLPROXY_HTTP;
    if (strtolower($type) == 'socks5') {
      $proxy_type = CURLPROXY_SOCKS5;
    } elseif (strtolower($type) == 'socks4') {
      $proxy_type = CURLPROXY_SOCKS4;
    } elseif (strtolower($type) == 'socks4a') {
      $proxy_type = CURLPROXY_SOCKS4A;
    }
    curl_setopt($ch, CURLOPT_PROXYTYPE, $proxy_type); // Specify proxy type
  }

  if (strpos($endpoint, 'https') !== false) {
    // curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1.2:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-SHA');
    // Check for TLS 1.3 support first (if available)
    if (defined('CURL_SSLVERSION_TLSv1_3') && $ssl === 3) {
      // var_dump("using TLSv3");
      curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_3); // CURL_SSLVERSION_TLSv1_3 = 7
    } // Check for TLS 1.2 support
    elseif (defined('CURL_SSLVERSION_TLSv1_2') && $ssl === 2) {
      curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    } elseif (defined('CURL_SSLVERSION_TLSv1_0') && $ssl === 1) {
      // var_dump("using TLSv1");
      curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_0); // CURL_SSLVERSION_TLSv1_0 = 4
    } elseif (defined('CURL_SSLVERSION_MAX_DEFAULT')) {
      curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_MAX_DEFAULT);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, 0);
    curl_setopt($ch, CURLOPT_CAINFO, realpath(__DIR__ . '/data/cacert.pem'));
    if (!empty($proxy)) {
      if (defined('CURLOPT_PROXY_SSL_VERIFYPEER')) {
        curl_setopt($ch, CURLOPT_PROXY_SSL_VERIFYPEER, 0);
      }
      if (defined('CURLOPT_PROXY_SSL_VERIFYHOST')) {
        curl_setopt($ch, CURLOPT_PROXY_SSL_VERIFYHOST, 0);
      }
    }
  }

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Set maximum connection time
  curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set maximum response time
  // curl_setopt($ch, CURLOPT_VERBOSE, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  // curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

  $cookies = __DIR__ . '/tmp/cookies/default.txt';
  if (!file_exists($cookies)) {
    write_file($cookies, '');
  }
  curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies); // Save cookies to file
  curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies); // Use cookies from file

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
 * Get the IP address of the server.
 *
 * This function attempts to retrieve the server's IP address using both PHP's
 * built-in global variables and system commands, ensuring compatibility with
 * both Linux and Windows operating systems.
 * If successful, it saves the IP address to a file. If the file already
 * exists and contains an IP address, it loads the IP address from the file.
 *
 * @return string|false The IP address as a string if found, or false if not found.
 */
function getServerIp()
{
  $filePath = __DIR__ . '/tmp/server-ip.txt';

  // Try to load IP from file if it exists and is not empty
  if (file_exists($filePath) && filesize($filePath) > 0) {
    $ipFromFile = trim(file_get_contents($filePath));
    if (!empty($ipFromFile)) {
      return $ipFromFile;
    }
  }

  // Check for server address
  if (!empty($_SERVER['SERVER_ADDR'])) {
    $serverIp = $_SERVER['SERVER_ADDR'];
    file_put_contents($filePath, $serverIp);
    return $serverIp;
  }

  // If the above fails, try to get the IP address from the system
  if (PHP_OS_FAMILY === 'Windows') {
    // Get the output from ipconfig and filter out IPv4 addresses
    $output = shell_exec("ipconfig");
    if ($output) {
      // Use regex to find all IPv4 addresses in the output
      preg_match_all('/IPv4 Address[^\d]*([\d\.]+)/i', $output, $matches);
      if (!empty($matches[1][0])) {
        $serverIp = trim($matches[1][0]);
        write_file($filePath, $serverIp);
        return $serverIp;
      }
    }
  } else {
    // For Linux, use hostname -I and filter out IPv6 addresses
    $ip = trim(shell_exec("hostname -I"));
    if ($ip) {
      // Split the result and find the first valid IPv4 address
      $ipParts = explode(' ', $ip);
      foreach ($ipParts as $part) {
        if (filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
          $serverIp = trim($part);
          file_put_contents($filePath, $serverIp);
          return $serverIp;
        }
      }
    }
  }

  return false;
}

/**
 * Obtain the anonymity of the proxy.
 *
 * @param string $response_ip_info The response containing IP information.
 * @param string $response_judges The response containing headers to judge anonymity.
 * @return string Anonymity level: Transparent, Anonymous, or Elite. And Empty is failed
 */
function parse_anonymity(string $response_ip_info, string $response_judges): string
{
  if (empty(trim($response_ip_info)) || empty(trim($response_judges))) {
    return "";
  }
  $mergedResponse = $response_ip_info . $response_judges;
  $deviceIp = getServerIp();
  if (empty($deviceIp) || $deviceIp === null || $deviceIp === false || $deviceIp === 0) {
    throw new Exception('Device IP is empty, null, false, or 0');
  }
  if (strpos($mergedResponse, $deviceIp) !== false) {
    return 'Transparent';
  }
  //  if (strpos($response_judges, $response_ip_info) !== false) {
  //    return 'Transparent';
  //  }

  $privacy_headers = [
    'VIA',
    'X-FORWARDED-FOR',
    'X-FORWARDED',
    'FORWARDED-FOR',
    'FORWARDED-FOR-IP',
    'FORWARDED',
    'CLIENT-IP',
    'PROXY-CONNECTION'
  ];

  foreach ($privacy_headers as $header) {
    if (strpos($response_judges, $header) !== false) {
      return 'Anonymous';
    }
  }

  return 'Elite';
}

/**
 * Get the anonymity level of a proxy using multiple judgment sources.
 *
 * @param string $proxy The proxy server address.
 * @param string $type The type of proxy (e.g., 'http', 'https').
 * @param string|null $username Optional username for proxy authentication.
 * @param string|null $password Optional password for proxy authentication.
 * @return string Anonymity level: Transparent, Anonymous, Elite, or Empty if failed.
 */
function get_anonymity(string $proxy, string $type, ?string $username = null, ?string $password = null): string
{
  $proxy_judges = [
    'https://wfuchs.de/azenv.php',
    'http://mojeip.net.pl/asdfa/azenv.php',
    'http://httpheader.net/azenv.php',
    'http://pascal.hoez.free.fr/azenv.php',
    'https://www.cooleasy.com/azenv.php',
    'https://httpbin.org/headers'
  ];
  $ip_infos = [
    'https://api.ipify.org/',
    'https://httpbin.org/ip',
    'https://cloudflare.com/cdn-cgi/trace'
  ];
  $content_judges = array_map(function (string $url) use ($proxy, $type, $username, $password): string {
    $ch = buildCurl($proxy, $type, $url, [], $username, $password);
    $content = curl_exec($ch);
    curl_close($ch);
    if (is_string($content)) {
      return $content;
    }
    return '';
  }, $proxy_judges);
  $content_ip = array_map(function (string $url) use ($proxy, $type, $username, $password): string {
    $ch = buildCurl($proxy, $type, $url, [], $username, $password);
    $content = curl_exec($ch);
    curl_close($ch);
    if ($content) {
      return $content;
    }
    return '';
  }, $ip_infos);
  return parse_anonymity(implode("\n", $content_ip), implode("\n", $content_judges));
}

/**
 * Check proxy connectivity.
 *
 * This function tests the connectivity of a given proxy by making a request to a specified endpoint.
 *
 * @param string $proxy The proxy address to test.
 * @param string $type (Optional) The type of proxy to use. Supported values: 'http', 'socks4', 'socks5', 'socks4a'.
 *                     Defaults to 'http' if not specified.
 * @param string $endpoint (Optional) The URL endpoint to test connectivity. Defaults to 'https://bing.com'.
 * @param array $headers (Optional) Additional HTTP headers to include in the request. Defaults to an empty array.
 * @return array An associative array containing the result of the proxy check:
 *               - 'result': Boolean indicating if the proxy check was successful.
 *               - 'latency': The latency (in milliseconds) of the proxy connection. If the connection failed, -1 is returned.
 *               - 'error': Error message if an error occurred during the connection attempt, null otherwise.
 *               - 'status': HTTP status code of the response.
 *               - 'private': Boolean indicating if the proxy is private.
 */
function checkProxy(
  string  $proxy,
  string  $type = 'http',
  string  $endpoint = 'https://bing.com',
  array   $headers = [],
  ?string $username = null,
  ?string $password = null,
  bool $multiSSL = false
) {
  $proxy = trim($proxy);
  if (!$multiSSL) {
    $ch = buildCurl($proxy, $type, $endpoint, $headers, $username, $password, "GET", null, 0);
    return processCheckProxy($ch, $proxy, $type, $username, $password);
  } else {
    $chs = [
      buildCurl($proxy, $type, $endpoint, $headers, $username, $password, "GET", null, 0),
      buildCurl($proxy, $type, $endpoint, $headers, $username, $password, "GET", null, 1),
      buildCurl($proxy, $type, $endpoint, $headers, $username, $password, "GET", null, 2),
      buildCurl($proxy, $type, $endpoint, $headers, $username, $password, "GET", null, 3)
    ];
    return array_map('processCheckProxy', $chs);
  }
}

function processCheckProxy($ch, $proxy, $type, $username, $password): array
{
  $endpoint = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
  curl_setopt($ch, CURLINFO_HEADER_OUT, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); // Timeout for connection phase in seconds
  curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Total timeout for the request in seconds
  $start = microtime(true); // Start time
  $response = curl_exec($ch);
  $end = microtime(true); // End time
  $request_headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
  $isHttps = strpos($endpoint, 'https') !== false;
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $http_status_valid = $http_status == 200 || $http_status == 201 || $http_status == 202 || $http_status == 204 ||
    $http_status == 301 || $http_status == 302 || $http_status == 304;
  $response_header = substr($response, 0, $header_size);
  $body = substr($response, $header_size);
  $info = curl_getinfo($ch);
  $latency = -1;

  // is private proxy?
  $isPrivate = stripos($response_header, 'Proxy-Authorization:') !== false;

  $result = [
    'result' => false,
    'body' => $response,
    'response-headers' => $response_header,
    'request-headers' => $request_headers,
    'proxy' => $proxy,
    'type' => $type
  ];

  // check if proxy not raw header
  if (is_string($body) && checkRawHeadersKeywords($body)) {
    // contains azenv headers
    $result['result'] = false;
    $result['error'] = 'azenv raw headers found';
  }

  // Check for CURL errors or empty response
  if (curl_errno($ch) || $response === false) {
    $error_msg = curl_error($ch);
    if (preg_match('/no authentication method was acceptable/mi', $error_msg)) {
      $isPrivate = true;
      $error_msg = "Need credentials";
    }
    $result = array_merge($result, [
      'result' => false,
      'latency' => $latency,
      'error' => $error_msg,
      'status' => trim($info['http_code']),
      'private' => $isPrivate,
      'https' => $isHttps,
      'anonymity' => null
    ]);
  }

  // var_dump('final url ' . $info['url']);

  // check proxy private by redirected to gateway url
  if (!$isPrivate && empty($result['error'])) {
    $finalUrl = $info['url'];
    $pattern = '/^https?:\/\/(?:www\.gstatic\.com|gateway\.(zs\w+)\.[a-zA-Z]{2,})(?::\d+)?\/.*(?:origurl)=/i';
    $is_private_match = preg_match($pattern, $finalUrl, $matches);
    $isPrivate = $is_private_match !== false && $is_private_match > 0;
    // mark as private dead
    if ($is_private_match) {
      $result['result'] = false;
      $result['status'] = trim($info['http_code']);
      $result['error'] = 'Private proxy ' . json_encode($matches);
      $result['private'] = true;
      $result['https'] = true; // private proxy always support HTTPS
      $result['anonymity'] = null;
    }
  }

  // if (empty($result['error'])) {
  //   if (!empty($body)) {
  //     $dom = \simplehtmldom\helper::str_get_html($body);
  //     echo "title: " . $dom->title() . PHP_EOL . PHP_EOL;
  //   }
  // }

  $result['curl'] = $ch;

  curl_close($ch);

  // Convert to milliseconds
  $latency = round(($end - $start) * 1000);

  // result is empty = no error
  if (empty($result['error'])) {
    $result = array_merge($result, [
      'result' => true,
      'latency' => $latency,
      'error' => null,
      'status' => trim($info['http_code']),
      'private' => $isPrivate,
      'https' => $isHttps,
      'anonymity' => null
    ]);
    if (!$http_status_valid) {
      $result['result'] = false;
      $result['error'] = "http response status code invalid $http_status";
    }
    $anonymity = get_anonymity($proxy, $type, $username, $password);
    if (!empty($anonymity)) {
      $result['anonymity'] = strtolower($anonymity);
    } else {
      $result['result'] = false;
      $result['error'] = 'failed obtain proxy anonymity';
    }
  }

  return $result;
}

/**
 * Check if the raw headers contain specific keywords like azenv.
 * @param string $input The raw string headers to check.
 */
function checkRawHeadersKeywords($input)
{
  // Define the keywords to check for
  $keywords = [
    "REMOTE_ADDR =",
    "REMOTE_PORT =",
    "REQUEST_METHOD =",
    "REQUEST_URI =",
    "HTTP_ACCEPT-LANGUAGE =",
    "HTTP_ACCEPT-ENCODING =",
    "HTTP_USER-AGENT =",
    "HTTP_ACCEPT =",
    "REQUEST_TIME =",
    "HTTP_UPGRADE-INSECURE-REQUESTS =",
    "HTTP_CONNECTION =",
    "HTTP_PRIORITY =",
  ];

  // Count how many keywords are found in the input
  $foundCount = 0;
  foreach ($keywords as $keyword) {
    if (strpos($input, $keyword) !== false) {
      $foundCount++;
    }
    // Early return if 4 keywords have been found
    if ($foundCount >= 4) {
      return true;
    }
  }

  return false;
}

function get_geo_ip(string $the_proxy, string $proxy_type = 'http', ?ProxyDB $db = null)
{
  $proxy = trim($the_proxy);
  if (empty($proxy)) {
    return;
  }
  if (empty($db)) {
    $db = new ProxyDB();
  }
  list($ip, $port) = explode(':', $proxy);
  /** @noinspection PhpFullyQualifiedNameUsageInspection */
  $geo_plugin = new \PhpProxyHunter\geoPlugin();
  $geoUrl = "https://ip-get-geolocation.com/api/json/$ip";
  // fetch ip info
  $content = curlGetWithProxy($geoUrl, $proxy, $proxy_type);
  if (!$content) {
    $content = '';
  }
  $geoIp = json_decode($content, true);
  $data = [];
  // Check if JSON decoding was successful
  if (json_last_error() === JSON_ERROR_NONE) {
    if (trim($geoIp['status']) != 'fail') {
      if (isset($geoIp['lat'])) {
        $data['latitude'] = $geoIp['lat'];
      }
      if (isset($geoIp['lon'])) {
        $data['longitude'] = $geoIp['lon'];
      }
      if (isset($geoIp['timezone'])) {
        $data['timezone'] = $geoIp['timezone'];
      }
      if (isset($geoIp['country'])) {
        $data['country'] = $geoIp['country'];
      }

      try {
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        $countries = array_values(\Annexare\Countries\countries());
        $filterCountry = array_filter($countries, function ($country) use ($geoIp, $proxy) {
          return trim(strtolower($country['name'])) == trim(strtolower($geoIp['country']));
        });
        if (!empty($filterCountry)) {
          $lang = array_values($filterCountry)[0]['languages'][0];
          if (!empty($lang)) {
            $db->updateData($proxy, ['lang' => $lang]);
          } else {
            echo "language $proxy is empty, country " . $geoIp['country'];
          }
        }
      } catch (Throwable $th) {
        echo $th->getMessage() . PHP_EOL;
      }

      if (isset($geoIp['region'])) {
        $region = $geoIp['region'];
        if (!empty($geoIp['regionName'])) {
          $region = $geoIp['regionName'];
        }
        $data['region'] = $region;
      }
    } else {
      $cache_file = curlGetCache($geoUrl);
      if (file_exists($cache_file)) {
        unlink($cache_file);
      }
    }
  } else {
    $locate = $geo_plugin->locate_recursive($ip);
    if (!empty($locate->countryName)) {
      $data['country'] = $locate->countryName;
    }
    if (!empty($locate->regionName)) {
      $data['region'] = $locate->regionName;
    }
    if (!empty($locate->latitude)) {
      $data['latitude'] = $locate->latitude;
    }
    if (!empty($locate->longitude)) {
      $data['longitude'] = $locate->longitude;
    }
    if (!empty($locate->timezone)) {
      $data['timezone'] = $locate->timezone;
    }
    $lang = $locate->lang;
    $locale = $locate->countryCode ? country_code_to_locale($locate->countryCode) : '';
    $ext_intl = $locate->countryCode ? ext_intl_get_lang_country_code($locate->countryCode) : '';
    if (!empty($locale)) {
      $lang = $locale;
    } elseif (!empty($ext_intl)) {
      $lang = $ext_intl;
    }
    if (!empty($lang)) {
      $data['lang'] = $lang;
    }
    // echo "$ip country $locate->countryName language is $lang" . PHP_EOL;
  }

  $db->updateData($proxy, $data);
}

/**
 * Retrieves the primary language based on the provided country code using the ext-intl extension.
 *
 * This function requires the PHP ext-intl extension to be enabled.
 *
 * @param string $country The country code.
 * @return string|null The primary language code or null if an error occurs or the language is not found.
 */
function ext_intl_get_lang_country_code(string $country): ?string
{
  if (empty($country)) {
    return null;
  }
  try {
    $subtags = ResourceBundle::create('likelySubtags', 'ICUDATA', false);
    $country = Locale::canonicalize('und_' . $country);
    if (($country[0] ?? null) === '_') {
      $country = 'und' . $country;
    }
    $locale = $subtags->get($country) ?: $subtags->get('und');
    return Locale::getPrimaryLanguage($locale);
  } catch (Exception $e) {
    return null;
  }
}

/**
 * Returns a locale from a provided country code.
 *
 * @param string $country_code ISO 3166-2-alpha 2 country code
 * @param string $language_code ISO 639-1-alpha 2 language code (optional)
 * @return string|null A locale, formatted like en_US, or null if not found
 */
function country_code_to_locale(string $country_code, string $language_code = ''): ?string
{
  if (empty($country_code)) {
    return null;
  }

  // Locale list taken from:
  // http://stackoverflow.com/questions/3191664/
  // list-of-all-locales-and-their-short-codes
  $locales = [
    'af-ZA',
    'am-ET',
    'ar-AE',
    'ar-BH',
    'ar-DZ',
    'ar-EG',
    'ar-IQ',
    'ar-JO',
    'ar-KW',
    'ar-LB',
    'ar-LY',
    'ar-MA',
    'arn-CL',
    'ar-OM',
    'ar-QA',
    'ar-SA',
    'ar-SY',
    'ar-TN',
    'ar-YE',
    'as-IN',
    'az-Cyrl-AZ',
    'az-Latn-AZ',
    'ba-RU',
    'be-BY',
    'bg-BG',
    'bn-BD',
    'bn-IN',
    'bo-CN',
    'br-FR',
    'bs-Cyrl-BA',
    'bs-Latn-BA',
    'ca-ES',
    'co-FR',
    'cs-CZ',
    'cy-GB',
    'da-DK',
    'de-AT',
    'de-CH',
    'de-DE',
    'de-LI',
    'de-LU',
    'dsb-DE',
    'dv-MV',
    'el-GR',
    'en-029',
    'en-AU',
    'en-BZ',
    'en-CA',
    'en-GB',
    'en-IE',
    'en-IN',
    'en-JM',
    'en-MY',
    'en-NZ',
    'en-PH',
    'en-SG',
    'en-TT',
    'en-US',
    'en-ZA',
    'en-ZW',
    'es-AR',
    'es-BO',
    'es-CL',
    'es-CO',
    'es-CR',
    'es-DO',
    'es-EC',
    'es-ES',
    'es-GT',
    'es-HN',
    'es-MX',
    'es-NI',
    'es-PA',
    'es-PE',
    'es-PR',
    'es-PY',
    'es-SV',
    'es-US',
    'es-UY',
    'es-VE',
    'et-EE',
    'eu-ES',
    'fa-IR',
    'fi-FI',
    'fil-PH',
    'fo-FO',
    'fr-BE',
    'fr-CA',
    'fr-CH',
    'fr-FR',
    'fr-LU',
    'fr-MC',
    'fy-NL',
    'ga-IE',
    'gd-GB',
    'gl-ES',
    'gsw-FR',
    'gu-IN',
    'ha-Latn-NG',
    'he-IL',
    'hi-IN',
    'hr-BA',
    'hr-HR',
    'hsb-DE',
    'hu-HU',
    'hy-AM',
    'id-ID',
    'ig-NG',
    'ii-CN',
    'is-IS',
    'it-CH',
    'it-IT',
    'iu-Cans-CA',
    'iu-Latn-CA',
    'ja-JP',
    'ka-GE',
    'kk-KZ',
    'kl-GL',
    'km-KH',
    'kn-IN',
    'kok-IN',
    'ko-KR',
    'ky-KG',
    'lb-LU',
    'lo-LA',
    'lt-LT',
    'lv-LV',
    'mi-NZ',
    'mk-MK',
    'ml-IN',
    'mn-MN',
    'mn-Mong-CN',
    'moh-CA',
    'mr-IN',
    'ms-BN',
    'ms-MY',
    'mt-MT',
    'nb-NO',
    'ne-NP',
    'nl-BE',
    'nl-NL',
    'nn-NO',
    'nso-ZA',
    'oc-FR',
    'or-IN',
    'pa-IN',
    'pl-PL',
    'prs-AF',
    'ps-AF',
    'pt-BR',
    'pt-PT',
    'qut-GT',
    'quz-BO',
    'quz-EC',
    'quz-PE',
    'rm-CH',
    'ro-RO',
    'ru-RU',
    'rw-RW',
    'sah-RU',
    'sa-IN',
    'se-FI',
    'se-NO',
    'se-SE',
    'si-LK',
    'sk-SK',
    'sl-SI',
    'sma-NO',
    'sma-SE',
    'smj-NO',
    'smj-SE',
    'smn-FI',
    'sms-FI',
    'sq-AL',
    'sr-Cyrl-BA',
    'sr-Cyrl-CS',
    'sr-Cyrl-ME',
    'sr-Cyrl-RS',
    'sr-Latn-BA',
    'sr-Latn-CS',
    'sr-Latn-ME',
    'sr-Latn-RS',
    'sv-FI',
    'sv-SE',
    'sw-KE',
    'syr-SY',
    'ta-IN',
    'te-IN',
    'tg-Cyrl-TJ',
    'th-TH',
    'tk-TM',
    'tn-ZA',
    'tr-TR',
    'tt-RU',
    'tzm-Latn-DZ',
    'ug-CN',
    'uk-UA',
    'ur-PK',
    'uz-Cyrl-UZ',
    'uz-Latn-UZ',
    'vi-VN',
    'wo-SN',
    'xh-ZA',
    'yo-NG',
    'zh-CN',
    'zh-HK',
    'zh-MO',
    'zh-SG',
    'zh-TW',
    'zu-ZA',
  ];

  foreach ($locales as $locale) {
    $locale_region = locale_get_region($locale);
    $locale_language = locale_get_primary_language($locale);
    $locale_array = [
      'language' => $locale_language,
      'region' => $locale_region
    ];

    if (
      strtoupper($country_code) == $locale_region &&
      $language_code == ''
    ) {
      return locale_compose($locale_array);
    } elseif (
      strtoupper($country_code) == $locale_region &&
      strtolower($language_code) == $locale_language
    ) {
      return locale_compose($locale_array);
    }
  }

  return null;
}

/**
 * Remove lines from a file that do not contain IP:PORT format.
 *
 * @param string $inputFile The path to the file.
 * @return string 'success' on successful filtering, or an error message on failure.
 */
function filterIpPortLines(string $inputFile): string
{
  // Check if destination file is writable
  if (!is_writable($inputFile)) {
    return "$inputFile not writable";
  }

  // Check if source file is locked
  if (is_file_locked($inputFile)) {
    return "$inputFile locked";
  }

  // Read content from file
  $content = read_file($inputFile);
  if (!is_string($content) || empty(trim($content))) {
    return "$inputFile could not be read or has empty content";
  }

  // Regex pattern for IP:PORT format
  $re = '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{2,5}\b/';

  // Split content into lines
  $lines = split_by_line($content);
  if (!$lines) {
    return "Failed to split content into lines";
  }

  // Filter lines based on regex pattern
  $filteredLines = [];
  foreach ($lines as $line) {
    if (preg_match($re, $line)) {
      $filteredLines[] = $line;
    }
  }

  // Write filtered lines back to the file
  if (file_put_contents($inputFile, implode("\n", $filteredLines)) === false) {
    return "Failed to write filtered content to $inputFile";
  }

  return 'success';
}

function clean_proxies_file(string $file)
{
  echo "remove duplicate lines $file" . PHP_EOL;

  removeDuplicateLines($file);

  echo "remove lines less than 10 size $file" . PHP_EOL;

  removeShortLines($file, 10);

  echo "remove lines not contains IP:PORT $file" . PHP_EOL;

  filterIpPortLines($file);

  echo "remove empty lines $file" . PHP_EOL;

  removeEmptyLinesFromFile($file);

  echo "fix file NUL $file" . PHP_EOL;

  fixFile($file);
}

/**
 * Parses working proxies data retrieved from the provided ProxyDB object.
 *
 * @param ProxyDB $db The ProxyDB object containing the working proxies data.
 * @return array An array containing three elements:
 *               - 'txt': A string representation of working proxies, separated by newline characters and formatted as "proxy|port|type|country|last_check|useragent".
 *               - 'array': An array of associative arrays representing the working proxies data, with keys 'proxy', 'port', 'type', 'country', 'last_check', and 'useragent'.
 *               - 'counter': An array containing counts of different types of proxies in the database, including 'working', 'dead', 'untested', and 'private'.
 */
function parse_working_proxies(ProxyDB $db): array
{
  // Retrieve working proxies from the provided ProxyDB object
  $working = $db->getWorkingProxies();

  // Sort working proxies by the newest last_check column
  usort($working, function ($a, $b) {
    return strtotime($b['last_check']) - strtotime($a['last_check']);
  });

  // Map proxies data
  $array_mapper = array_map(function ($item) use ($db) {
    // Fill empty values with '-'
    foreach ($item as $key => $value) {
      if (empty($value)) {
        $item[$key] = '-';
      }
    }

    // Remove unneeded property
    unset($item['id']);

    // Uppercase proxy type
    $item['type'] = strtoupper($item['type']);

    // Update metadata info
    if (empty($item['useragent']) && strlen(trim($item['useragent'])) <= 5) {
      $item['useragent'] = randomWindowsUa();
      $db->updateData($item['proxy'], $item);
      // Re-fetch geolocation IP
      get_geo_ip($item['proxy']);
    }

    return $item;
  }, $working);

  // Format proxies data for working.txt file, separating each proxy by '|'
  $workingTxt = implode(PHP_EOL, array_map(function ($item) {
    return implode('|', $item);
  }, $array_mapper));

  $count = [
    'working' => $db->countWorkingProxies(),
    'dead' => $db->countDeadProxies(),
    'untested' => $db->countUntestedProxies(),
    'private' => $db->countPrivateProxies(),
    'all' => $db->countAllProxies()
  ];

  return ['txt' => $workingTxt, 'array' => $array_mapper, 'counter' => $count];
}


/**
 * Extracts IP:PORT combinations from a file and processes each match using a callback function.
 *
 * @param string $filePath The path to the file containing IP:PORT combinations.
 * @param callable $callback The callback function to process each matched IP:PORT combination.
 * @throws Exception
 */
function extractIpPortFromFileCallback(string $filePath, callable $callback)
{
  if (file_exists($filePath)) {
    // Open the file for reading in binary mode
    $fp = fopen($filePath, "rb");
    if (!$fp) {
      throw new Exception('File open failed.');
    }

    // Read file line by line
    while (!feof($fp)) {
      $line = fgets($fp);

      // Match IP:PORT pattern using regular expression
      preg_match_all('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+\b/', $line, $matches);

      // Process each matched IP:PORT combination using the callback function
      foreach ($matches[0] as $match) {
        $proxy = trim($match);
        if (empty($proxy) || is_null($proxy)) {
          continue;
        }
        $callback($proxy);
      }
    }

    // Close the file
    fclose($fp);
  }
}


/**
 * Extracts IP:PORT combinations from a file.
 *
 * @param string $filePath The path to the file containing IP:PORT combinations.
 * @param bool $unique (Optional) If set to true, returns only unique IP:PORT combinations. Default is false.
 * @return array An array containing the extracted IP:PORT combinations.
 */
function extractIpPortFromFile(string $filePath, bool $unique = false): array
{
  $ipPortList = [];

  if (file_exists($filePath)) {
    // Open the file for reading in binary mode
    $fp = @fopen($filePath, "rb");
    if (!$fp) {
      return [];
    }

    // Read file line by line
    while (!feof($fp)) {
      $line = fgets($fp);

      // Match IP:PORT pattern using regular expression
      preg_match_all('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+\b/', $line, $matches);

      // Add matched IP:PORT combinations to the list
      foreach ($matches[0] as $match) {
        $ipPortList[] = trim($match);
      }
    }

    // Close the file
    fclose($fp);
  }

  if ($unique) {
    $ipPortList = array_unique($ipPortList);
  }

  return $ipPortList;
}

function generateIPWithPorts($ip, $minPort = 10, $maxPort = 65535)
{
  // Initialize an empty array to hold the IP:PORT values
  $ipPorts = [];

  // Loop from port 80 to the maximum port value
  for ($port = $minPort; $port <= $maxPort; $port++) {
    // Add the IP:PORT value to the array
    $ipPorts[] = $ip . ':' . $port;
  }

  return $ipPorts;
}
