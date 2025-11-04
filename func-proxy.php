<?php

/** @noinspection RegExpRedundantEscape */
/** @noinspection RegExpUnnecessaryNonCapturingGroup */

require_once __DIR__ . '/func.php';

/**
 * Merge two arrays of HTTP headers while ensuring uniqueness based on the keys.
 *
 * @param array $defaultHeaders The array of default headers.
 * @param array $additionalHeaders The array of additional headers to merge.
 * @return array The merged array of headers with unique keys.
 */
function mergeHeaders($defaultHeaders, $additionalHeaders)
{
  // Convert the arrays into associative arrays with header keys as keys
  $convertToAssocArray = function ($headers) {
    $assocArray = [];
    foreach ($headers as $header) {
      $parts                 = explode(': ', $header, 2);
      $assocArray[$parts[0]] = isset($parts[1]) ? $parts[1] : '';
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
  $filePath = __DIR__ . '/tmp/locks/server-ip.txt';
  $result   = false;

  // Delete cached IP file if it's older than 5 mins on debug devices
  if (is_debug_device() && file_exists($filePath) && (time() - filemtime($filePath) > 300)) {
    unlink($filePath);
  }

  // Try to load IP from file if it exists and is not empty
  if (file_exists($filePath) && filesize($filePath) > 0) {
    $ipFromFile = trim(file_get_contents($filePath));
    if (!empty($ipFromFile)) {
      $result = $ipFromFile;
    }
  }

  // Check for server address
  if (empty($result) && !empty($_SERVER['SERVER_ADDR'])) {
    $serverIp = $_SERVER['SERVER_ADDR'];
    file_put_contents($filePath, $serverIp);
    $result = $serverIp;
  }

  // If the above fails, try to get the IP address from the system
  if (empty($result) && PHP_OS_FAMILY === 'Windows') {
    // Get the output from ipconfig and filter out IPv4 addresses
    $output = shell_exec('ipconfig');
    if ($output) {
      // Use regex to find all IPv4 addresses in the output
      preg_match_all('/IPv4 Address[^\d]*([\d\.]+)/i', $output, $matches);
      if (!empty($matches[1][0])) {
        $serverIp = trim($matches[1][0]);
        write_file($filePath, $serverIp);
        $result = $serverIp;
      }
    }
  } elseif (empty($result)) {
    // For Linux, use hostname -I and filter out IPv6 addresses
    $ip = trim(shell_exec('hostname -I'));
    if ($ip) {
      // Split the result and find the first valid IPv4 address
      $ipParts = explode(' ', $ip);
      foreach ($ipParts as $part) {
        if (filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
          $serverIp = trim($part);
          file_put_contents($filePath, $serverIp);
          $result = $serverIp;
        }
      }
    }
  }

  $isCurrentIpIsLocal = !empty($result) && preg_match('/^(192\.168|127\.)/', $result) === 1;
  if ($isCurrentIpIsLocal) {
    // Try to get public IP if current IP is a common router IP
    $external_ip = getPublicIP(false, 10);
    if ($external_ip !== false && filter_var($external_ip, FILTER_VALIDATE_IP)) {
      $result = $external_ip;
    }
  }

  return $result;
}

/**
 * Obtain the anonymity of the proxy.
 *
 * @param string $response_ip_info The response containing IP information.
 * @param string $response_judges The response containing headers to judge anonymity.
 * @return string Anonymity level: Transparent, Anonymous, or Elite. And Empty is failed
 */
/**
 * @param string $response_ip_info
 * @param string $response_judges
 * @return string
 */
function parse_anonymity($response_ip_info, $response_judges)
{
  if (empty(trim($response_ip_info)) || empty(trim($response_judges))) {
    return '';
  }
  $mergedResponse = $response_ip_info . $response_judges;
  $deviceIp       = getServerIp();
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
    'PROXY-CONNECTION',
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
/**
 * @param string $proxy
 * @param string $type
 * @param string|null $username
 * @param string|null $password
 * @return string
 */
function get_anonymity($proxy, $type, $username = null, $password = null)
{
  $proxy_judges = [
    'https://wfuchs.de/azenv.php',
    'http://mojeip.net.pl/asdfa/azenv.php',
    'http://httpheader.net/azenv.php',
    'http://pascal.hoez.free.fr/azenv.php',
    'https://www.cooleasy.com/azenv.php',
    'https://httpbin.org/headers',
  ];
  $ip_infos = [
    'https://api.ipify.org/',
    'https://httpbin.org/ip',
    'https://cloudflare.com/cdn-cgi/trace',
  ];
  $content_judges = array_map(function ($url) use ($proxy, $type, $username, $password) {
    $ch      = buildCurl($proxy, $type, $url, [], $username, $password);
    $content = curl_exec($ch);
    curl_close($ch);
    if ($content !== false && is_string($content)) {
      return $content;
    }
    return '';
  }, $proxy_judges);
  $content_ip = array_map(function ($url) use ($proxy, $type, $username, $password) {
    $ch      = buildCurl($proxy, $type, $url, [], $username, $password);
    $content = curl_exec($ch);
    curl_close($ch);
    if ($content !== false && is_string($content)) {
      return $content;
    }
    return '';
  }, $ip_infos);
  return parse_anonymity(implode("\n", $content_ip), implode("\n", $content_judges));
}

/**
 * Check proxy connectivity.
 *
 * Tests the connectivity of a given proxy by making a request to a specified endpoint.
 * Optionally supports authentication and multiple SSL verification modes.
 *
 * @param string      $proxy     The proxy address to test.
 * @param string      $type      (Optional) The type of proxy to use.
 *                               Supported values: 'http', 'socks4', 'socks5', 'socks4a'.
 *                               Defaults to 'http' if not specified.
 * @param string      $endpoint  (Optional) The URL endpoint to test connectivity.
 *                               Defaults to 'https://bing.com'.
 * @param array       $headers   (Optional) Additional HTTP headers to include in the request.
 *                               Defaults to an empty array.
 * @param string|null $username  (Optional) The username for proxy authentication. Defaults to null.
 * @param string|null $password  (Optional) The password for proxy authentication. Defaults to null.
 * @param bool        $multiSSL  (Optional) Whether to test multiple SSL configurations.
 *                               Defaults to false.
 *
 * @return array An associative array containing the result(s) of the proxy check:
 *               If `$multiSSL` is false:
 *                 - 'result'  (bool):   Indicates if the proxy check was successful.
 *                 - 'latency' (int):    Latency in milliseconds. Returns -1 on failure.
 *                 - 'error'   (string): Error message if an error occurred, otherwise null.
 *                 - 'status'  (int):    HTTP status code of the response.
 *                 - 'private' (bool):   Indicates if the proxy is private.
 *               If `$multiSSL` is true:
 *                 Returns an array of results for each SSL variant tested.
 */
function checkProxy(
  $proxy,
  $type = 'http',
  $endpoint = 'https://bing.com',
  $headers = [],
  $username = null,
  $password = null,
  $multiSSL = false
) {
  $proxy = trim($proxy);
  if (!$multiSSL) {
    $ch = buildCurl($proxy, $type, $endpoint, $headers, $username, $password, 'GET', null, 0);
    return processCheckProxy($ch, $proxy, $type, $username, $password);
  } else {
    $chs = [
      buildCurl($proxy, $type, $endpoint, $headers, $username, $password, 'GET', null, 0),
      buildCurl($proxy, $type, $endpoint, $headers, $username, $password, 'GET', null, 1),
      buildCurl($proxy, $type, $endpoint, $headers, $username, $password, 'GET', null, 2),
      buildCurl($proxy, $type, $endpoint, $headers, $username, $password, 'GET', null, 3),
    ];
    return array_map('processCheckProxy', $chs);
  }
}

/**
 * @param resource $ch
 * @param string $proxy
 * @param string $type
 * @param string $username
 * @param string $password
 * @return array
 */
function processCheckProxy($ch, $proxy, $type, $username, $password)
{
  $endpoint = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
  curl_setopt($ch, CURLINFO_HEADER_OUT, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); // Timeout for connection phase in seconds
  curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Total timeout for the request in seconds
  $start             = microtime(true); // Start time
  $response          = curl_exec($ch);
  $end               = microtime(true); // End time
  $request_headers   = curl_getinfo($ch, CURLINFO_HEADER_OUT);
  $isHttps           = strpos($endpoint, 'https') !== false;
  $header_size       = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $http_status       = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $http_status_valid = $http_status == 200 || $http_status == 201 || $http_status == 202 || $http_status == 204 || $http_status == 301 || $http_status == 302 || $http_status == 304;
  if ($response !== false) {
    $response_header = substr($response, 0, $header_size);
    $body            = substr($response, $header_size);
  } else {
    // If the response is false, set empty strings for headers and body
    $response_header = '';
    $body            = '';
  }
  $info    = curl_getinfo($ch);
  $latency = -1;

  // is private proxy?
  $isPrivate = stripos($response_header, 'Proxy-Authorization:') !== false;

  $result = [
    'result'           => false,
    'body'             => $response,
    'response-headers' => $response_header,
    'request-headers'  => $request_headers,
    'proxy'            => $proxy,
    'type'             => $type,
  ];

  // Check for azenv/raw headers or empty body
  if (empty($body) || !is_string($body)) {
    $result['result'] = false;
    $result['error']  = 'empty response body';
  } elseif (
    checkRawHeadersKeywords($body) || stripos($response_header, 'azenvironment') !== false || stripos($response_header, 'azenv') !== false
  ) {
    $result['result'] = false;
    $result['error']  = 'azenv raw headers found';
  }

  // Check for CURL errors or empty response
  if (curl_errno($ch) || $response === false) {
    $error_msg = curl_error($ch);
    if (preg_match('/no authentication method was acceptable/mi', $error_msg)) {
      $isPrivate = true;
      $error_msg = 'Need credentials';
    }
    $result = array_merge($result, [
      'result'    => false,
      'latency'   => $latency,
      'error'     => $error_msg,
      'status'    => (string)$info['http_code'],
      'private'   => $isPrivate,
      'https'     => $isHttps,
      'anonymity' => null,
    ]);
  }

  // var_dump('final url ' . $info['url']);

  // check proxy private by redirected to gateway url
  if (!$isPrivate && empty($result['error'])) {
    $finalUrl         = $info['url'];
    $pattern          = '/^https?:\/\/(?:www\.gstatic\.com|gateway\.(zs\w+)\.[a-zA-Z]{2,})(?::\d+)?\/.*(?:origurl)=/i';
    $is_private_match = preg_match($pattern, $finalUrl, $matches);
    $isPrivate        = $is_private_match !== false && $is_private_match > 0;
    // mark as private dead
    if ($is_private_match) {
      $result['result']    = false;
      $result['status']    = (string)$info['http_code'];
      $result['error']     = 'Private proxy ' . json_encode($matches);
      $result['private']   = true;
      $result['https']     = true; // private proxy always support HTTPS
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
      'result'    => true,
      'latency'   => $latency,
      'error'     => null,
      'status'    => (string)$info['http_code'],
      'private'   => $isPrivate,
      'https'     => $isHttps,
      'anonymity' => null,
    ]);
    if (!$http_status_valid) {
      $result['result'] = false;
      $result['error']  = "http response status code invalid $http_status";
    }
    $anonymity = get_anonymity($proxy, $type, $username, $password);
    if (!empty($anonymity)) {
      $result['anonymity'] = strtolower($anonymity);
    } else {
      $result['result'] = false;
      $result['error']  = 'failed obtain proxy anonymity';
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
    'REMOTE_ADDR =',
    'REMOTE_PORT =',
    'REQUEST_METHOD =',
    'REQUEST_URI =',
    'HTTP_ACCEPT-LANGUAGE =',
    'HTTP_ACCEPT-ENCODING =',
    'HTTP_USER-AGENT =',
    'HTTP_ACCEPT =',
    'REQUEST_TIME =',
    'HTTP_UPGRADE-INSECURE-REQUESTS =',
    'HTTP_CONNECTION =',
    'HTTP_PRIORITY =',
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

/**
 * Remove lines from a file that do not contain IP:PORT format.
 *
 * @param string $inputFile The path to the file.
 * @return string 'success' on successful filtering, or an error message on failure.
 */
/**
 * @param string $inputFile
 * @return string
 */
function filterIpPortLines($inputFile)
{
  // Check if destination file is writable
  if (!is_writable($inputFile)) {
    return "$inputFile not writable";
  }

  // Check if source file is locked
  if (is_file_locked($inputFile)) {
    return "$inputFile locked";
  }

  // Regex pattern for IP:PORT format
  $re = '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{2,5}\b/';

  $tmpFile = $inputFile . '.tmp';
  $in      = fopen($inputFile, 'r');
  if (!$in) {
    return "$inputFile could not be opened for reading";
  }
  $out = fopen($tmpFile, 'w');
  if (!$out) {
    fclose($in);
    return "$tmpFile could not be opened for writing";
  }
  $found = false;
  while (($line = fgets($in)) !== false) {
    if (preg_match($re, $line)) {
      fwrite($out, $line);
      $found = true;
    }
  }
  fclose($in);
  fclose($out);
  if ($found) {
    if (!rename($tmpFile, $inputFile)) {
      unlink($tmpFile);
      return "Failed to overwrite $inputFile with filtered content";
    }
  } else {
    unlink($tmpFile);
    return "$inputFile has no valid proxy lines";
  }
  return 'success';
}

/**
 * @param string $file
 */
function clean_proxies_file($file)
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
/**
 * @param ProxyDB $db
 * @return array
 */
function parse_working_proxies($db)
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
      \PhpProxyHunter\GeoIpHelper::getGeoIp($item['proxy']);
    }

    return $item;
  }, $working);

  // Format proxies data for working.txt file, separating each proxy by '|'
  $workingTxt = implode(PHP_EOL, array_map(function ($item) {
    return implode('|', $item);
  }, $array_mapper));

  $count = [
    'working'  => $db->countWorkingProxies(),
    'dead'     => $db->countDeadProxies(),
    'untested' => $db->countUntestedProxies(),
    'private'  => $db->countPrivateProxies(),
    'all'      => $db->countAllProxies(),
  ];

  return ['txt' => $workingTxt, 'array' => $array_mapper, 'counter' => $count];
}

/**
 * Writes working proxies data to files in both text and JSON formats.
 *
 * This function acquires an exclusive lock via a lock file to avoid concurrent
 * writers. You can provide a custom lock file path by passing the optional
 * $lock_file parameter. If omitted, a default lock file under `tmp/locks`
 * will be used.
 *
 * @param ProxyDB $db The ProxyDB object containing the working proxies data.
 * @param string|null $lock_file Optional absolute path to a lock file. If null,
 *                              defaults to __DIR__ . '/tmp/locks/writing-working-proxies.lock'.
 * @return array An array containing three elements: parsed working proxies and counters.
 */
function writing_working_proxies_file($db, $lock_file = null)
{
  $lock_file = $lock_file ?? (__DIR__ . '/tmp/locks/writing-working-proxies.lock');
  // func-proxy.php lives in project root, so __DIR__ is the project root.
  // Use __DIR__ here instead of dirname(__DIR__) which points to the parent folder.
  $projectRoot    = __DIR__;
  $workingProxies = parse_working_proxies($db);
  // Ensure lock dir exists
  $lockDir = dirname($lock_file);
  if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0777, true);
  }

  // Simple file-based lock logic:
  // - If lock file already exists, another process is writing: return immediately.
  // - Otherwise create the lock file (atomic with FILE_EX) and proceed.
  // - Ensure the lock file is removed in the finally block.
  if (file_exists($lock_file)) {
    return $workingProxies;
  }

  $lockWritten = false;
  try {
    // Attempt to create the lock file atomically
    if (@file_put_contents($lock_file, (string) getmypid(), LOCK_EX) === false) {
      // Could not create lock file — skip writing
      return $workingProxies;
    }
    $lockWritten = true;

    // Atomic writes: write to temp files and rename
    $files = [
      $projectRoot . '/working.txt'  => $workingProxies['txt'],
      $projectRoot . '/working.json' => json_encode($workingProxies['array']),
      $projectRoot . '/status.json'  => json_encode($workingProxies['counter']),
    ];

    foreach ($files as $path => $content) {
      $tmp = $path . '.tmp';
      if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        if (file_exists($tmp)) {
          @unlink($tmp);
        }
        // Writing failed — abort and return without throwing
        return $workingProxies;
      }
      if (!@rename($tmp, $path)) {
        @unlink($tmp);
        // Rename failed — abort and return without throwing
        return $workingProxies;
      }
    }
  } finally {
    if ($lockWritten && file_exists($lock_file)) {
      @unlink($lock_file);
    }
  }
  return $workingProxies;
}

/**
 * Extracts IP:PORT combinations from a file and processes each match using a callback function.
 *
 * @param string $filePath The path to the file containing IP:PORT combinations.
 * @param callable $callback The callback function to process each matched IP:PORT combination.
 * @throws Exception
 */
/**
 * @param string $filePath
 * @param callable $callback
 * @throws Exception
 */
function extractIpPortFromFileCallback($filePath, $callback)
{
  if (file_exists($filePath)) {
    // Open the file for reading in binary mode
    $fp = fopen($filePath, 'rb');
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
/**
 * @param string $filePath
 * @param bool $unique
 * @return array
 */
function extractIpPortFromFile($filePath, $unique = false)
{
  $ipPortList = [];

  if (file_exists($filePath)) {
    // Open the file for reading in binary mode
    $fp = @fopen($filePath, 'rb');
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

/**
 * @param string $ip
 * @param int $minPort
 * @param int $maxPort
 * @return array
 */
function generateIPWithPorts($ip, $minPort = 10, $maxPort = 65535)
{
  // Initialize an empty array to hold the IP:PORT values
  $ipPorts = [];

  // Loop from port 80 to the maximum port value
  for ($port = $minPort; $port <= $maxPort; $port++) {
    $ipPorts[] = $ip . ':' . $port;
  }

  return $ipPorts;
}
