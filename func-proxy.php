<?php

require_once __DIR__ . '/func.php';

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

/**
 * Extracts IP:PORT pairs from a string, along with optional username and password.
 *
 * @param string $string The input string containing IP:PORT pairs.
 * @return Proxy[] An array containing the extracted IP:PORT pairs along with username and password if present.
 */
function extractProxies(string $string): array
{
  $results = [];

  // Regular expression pattern to match IP:PORT pairs along with optional username and password
  /** @noinspection RegExpUnnecessaryNonCapturingGroup */
  /** @noinspection RegExpRedundantEscape */
  $pattern = '/((?:(?:\d{1,3}\.){3}\d{1,3})\:\d{2,5}(?:@\w+:\w+)?|(?:(?:\w+)\:\w+@\d{1,3}(?:\.\d{1,3}){3}\:\d{2,5}))/';

  // Perform the matching
  preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);

  // Extracted IP:PORT pairs along with optional username and password
  // $ipPorts = [];
  $db = new ProxyDB();
  foreach ($matches as $match) {
    $username = $password = $proxy = null;
    if (!empty($match[1]) && strpos($match[1], '@') !== false) {
      list($proxy, $login) = explode('@', $match[1]);
      $_login = $login;
      if (!isValidIPPort($proxy)) {
        $login = $proxy;
        $proxy = $_login;
      }
      // var_dump("$proxy@$login");
      list($username, $password) = explode(":", $login);
    } else {
      $proxy = $match[0];
    }
    // var_dump("$username and $password");

    if ($proxy != null) {
      $select = $db->select($proxy);
      if (!empty($select)) {
        $result = array_map(function ($item) use ($username, $password) {
          $wrap = new Proxy($item['proxy']);
          foreach ($item as $key => $value) {
            if (property_exists($wrap, $key)) {
              $wrap->$key = $value;
            }
          }
          if (!is_null($username) && !is_null($password)) {
            $wrap->username = $username;
            $wrap->password = $password;
          }
          return $wrap;
        }, $select);
        $results[] = $result[0];
      } else {
        $result = new Proxy($proxy);
        if (!is_null($username) && !is_null($password)) {
          $result->username = $username;
          $result->password = $password;
          $db->updateData($proxy, ['username' => $username, 'password' => $password]);
        } else {
          $db->add($proxy);
        }
        $results[] = $result;
      }
    }
  }

  return $results;
}

/**
 * Checks if a string is in the format of an IP address followed by a port number.
 *
 * @param string $str The string to check.
 * @return bool Returns true if the string is in the format of IP:PORT, otherwise false.
 */
function isValidIPPort(string $str): bool
{
  $str = trim($str);
  // Regular expression to match IP:PORT format
  /** @noinspection RegExpUnnecessaryNonCapturingGroup */
  $pattern = '/^(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?):(?:\d{1,5})$/';

  // Check if the string matches the pattern
  if (preg_match($pattern, $str)) {
    return true;
  } else {
    return false;
  }
}

/**
 * Check if a port is open on a given IP address.
 *
 * @param string $proxy The IP address and port to check in the format "IP:port".
 * @param int $timeout The timeout value in seconds (default is 10 seconds).
 * @return bool True if the port is open, false otherwise.
 */
function isPortOpen(string $proxy, int $timeout = 10): bool
{
  $proxy = trim($proxy);

  // disallow empty proxy
  if (empty($proxy) || strlen($proxy) < 7)
    return false;

  // Separate IP and port
  list($ip, $port) = explode(':', $proxy);

  // Create a TCP/IP socket with the specified timeout
  $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

  // Check if the socket could be opened
  if ($socket === false) {
    return false; // Port is closed
  } else {
    fclose($socket);
    return true; // Port is open
  }
}

function buildCurl($proxy, $type = 'http', string $endpoint = 'https://bing.com', array $headers = [], ?string $username = null, ?string $password = null)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint); // URL to test connectivity
  curl_setopt($ch, CURLOPT_PROXY, $proxy); // Proxy address
  if (!is_null($username) && !is_null($password)) {
    curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$username:$password"); // Set proxy authentication credentials
  }

  // Determine the CURL proxy type based on the specified $type
  $proxy_type = CURLPROXY_HTTP;
  if (strtolower($type) == 'socks5')
    $proxy_type = CURLPROXY_SOCKS5;
  if (strtolower($type) == 'socks4')
    $proxy_type = CURLPROXY_SOCKS4;
  if (strtolower($type) == 'socks4a')
    $proxy_type = CURLPROXY_SOCKS4A;
  curl_setopt($ch, CURLOPT_PROXYTYPE, $proxy_type); // Specify proxy type

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Set maximum connection time
  curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set maximum response time

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);

  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);

  $cookies = __DIR__ . '/tmp/cookies/' . sanitizeFilename($proxy) . '.txt';
  if (!file_exists(dirname($cookies)))
    mkdir(dirname($cookies), 0777, true);
  curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
  curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);

  $userAgent = randomAndroidUa();

  foreach ($headers as $header) {
    if (strpos($header, 'User-Agent:') === 0) {
      $userAgent = trim(substr($header, strlen('User-Agent:')));
      break;
    }
  }

  if (empty($userAgent))
    $userAgent = randomAndroidUa();

  curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate'); // Handle compressed response
  return $ch;
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
function checkProxy(string $proxy, string $type = 'http', string $endpoint = 'https://bing.com', array $headers = [], ?string $username = null, ?string $password = null): array
{
  $proxy = trim($proxy);
  $ch = buildCurl($proxy, $type, $endpoint, $headers, $username, $password);
  $start = microtime(true); // Start time
  $response = curl_exec($ch);
  $end = microtime(true); // End time

  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $response_header = substr($response, 0, $header_size);
  // is private proxy?
  $isPrivate = stripos($response_header, 'X-Forwarded-For:') !== false || stripos($response_header, 'Proxy-Authorization:') !== false;

  $info = curl_getinfo($ch);
  $latency = -1;

  $result = [];

  // Check for CURL errors or empty response
  if (curl_errno($ch) || $response === false) {
    $error_msg = curl_error($ch);
    $result = [
        'result' => false,
        'latency' => $latency,
        'error' => $error_msg,
        'status' => $info['http_code'],
        'private' => $isPrivate
    ];
  }

  curl_close($ch);

  // Convert to milliseconds
  $latency = round(($end - $start) * 1000);

  if (empty($result)) {
    $result = [
        'result' => true,
        'latency' => $latency,
        'error' => null,
        'status' => $info['http_code'],
        'private' => $isPrivate
    ];
  }

  return $result;
}

function get_geo_ip(string $proxy, string $proxy_type = 'http')
{
  $proxy = trim($proxy);
  $db = new ProxyDB();
  list($ip, $port) = explode(':', $proxy);
  $geoUrl = "https://ip-get-geolocation.com/api/json/$ip";
  // fetch ip info
  $geoIp = json_decode(curlGetWithProxy($geoUrl, $proxy, $proxy_type), true);
  // Check if JSON decoding was successful
  if (json_last_error() === JSON_ERROR_NONE) {
    if (trim($geoIp['status']) != 'fail') {
      $data = [];
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
      if (isset($geoIp['region'])) {
        $region = $geoIp['region'];
        if (!empty($geoIp['regionName'])) $region = $geoIp['regionName'];
        $data['region'] = $region;
      }
      $db->updateData($proxy, $data);
    } else {
      $cache_file = curlGetCache($geoUrl);
      if (file_exists($cache_file)) unlink($cache_file);
    }
  }
}