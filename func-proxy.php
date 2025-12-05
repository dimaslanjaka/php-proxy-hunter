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
function mergeHeaders($defaultHeaders, $additionalHeaders) {
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
 * @return array<string,mixed>|array<int,array<string,mixed>>
 *   When `$multiSSL` is false this returns an associative result array describing
 *   the check. When `$multiSSL` is true this returns a numerically indexed
 *   array of result arrays — one entry per SSL variant tested.
 *
 *   Each result array may contain the following keys (most are populated by
 *   `processCheckProxy()`):
 *     - result (bool):        true when the proxy check succeeded, false otherwise.
 *     - latency (int):        Round-trip time in milliseconds, -1 on failure.
 *     - duration (float):     Duration in seconds (fractional) for higher precision.
 *     - executed_at (string): ISO8601 timestamp when the request was executed.
 *     - error (string|null):  Error message when result is false, otherwise null.
 *     - status (string):      HTTP status code as string.
 *     - http_status_int (int): HTTP status code as integer.
 *     - private (bool):       Whether the proxy appears to be a private/authenticated gateway.
 *     - https (bool):         Whether the effective URL/scheme was HTTPS.
 *     - anonymity (string|null): Detected anonymity level (e.g. 'transparent','anonymous','elite') or null.
 *     - body (string|false):  Raw response body or false when execution failed.
 *     - body_snippet (string): First N characters of the body for quick inspection.
 *     - response-headers (string): Raw response headers.
 *     - http_headers_parsed (array): Parsed response headers as associative array.
 *     - request-headers (string):  Raw request headers sent (when available).
 *     - curl_info (array):    Result of `curl_getinfo()` captured before closing the handle.
 *     - effective_url (string): Final resolved URL after redirects.
 *     - proxy (string):       The proxy that was tested.
 *     - type (string):        The proxy type used for the request.
 *     - ssl_variant (int|null): When `multiSSL` is used, the SSL variant index (0..3).
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
    $results = [];
    foreach ($chs as $i => $ch) {
      $res = processCheckProxy($ch, $proxy, $type, $username, $password);
      if (is_array($res)) {
        $res['ssl_variant'] = $i;
      }
      $results[] = $res;
    }
    return $results;
  }
}

/**
 * Process the executed cURL handle and return a detailed result array.
 *
 * @param resource|\CurlHandle $ch      The initialized and executed cURL handle.
 * @param string                $proxy  The proxy string used for the request (e.g. "1.2.3.4:8080").
 * @param string                $type   Proxy type (e.g. "http", "socks5").
 * @param string                $username Optional proxy username.
 * @param string                $password Optional proxy password.
 *
 * @return array<string,mixed> Returns an associative array with the following keys:
 *   - result (bool):        true when the proxy check succeeded, false otherwise.
 *   - latency (int):        Round-trip time in milliseconds, -1 on failure.
 *   - error (string|null):  Error message when result is false, otherwise null.
 *   - status (string):      HTTP status code returned by the request (as string).
 *   - private (bool):       Whether the proxy appears to be a private/authenticated gateway.
 *   - https (bool):         Whether the effective URL/scheme was HTTPS.
 *   - anonymity (string|null): Detected anonymity level (e.g. 'transparent','anonymous','elite') or null.
 *   - body (string|false):  Raw response body or false when execution failed.
 *   - response-headers (string): Raw response headers.
 *   - request-headers (string):  Raw request headers sent (when available).
 *   - proxy (string):       The proxy that was tested.
 *   - type (string):        The proxy type that was used for the request.
 *   - duration (float):     Duration in seconds (fractional) for higher precision.
 *   - executed_at (string): ISO8601 timestamp when the request was executed.
 *   - http_headers_parsed (array): Parsed response headers as associative array.
 *   - curl_info (array):    Result of `curl_getinfo()` captured before closing the handle.
 *   - effective_url (string): Final resolved URL after redirects.
 *   - ssl_variant (int|null): SSL variant index (0..3) when applicable.
 */
function processCheckProxy($ch, $proxy, $type, $username, $password) {
  $endpoint     = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
  $optHeaderOut = defined('CURLOPT_HEADER_OUT') ? constant('CURLOPT_HEADER_OUT') : null;
  if ($optHeaderOut !== null) {
    curl_setopt($ch, $optHeaderOut, true);
  }
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
  // Timeout for connection phase in seconds
  curl_setopt($ch, CURLOPT_TIMEOUT, 60);
  // Total timeout for the request in seconds
  $start = microtime(true);
  // Start time
  $response = curl_exec($ch);
  $end      = microtime(true);
  // End time

  // Get full curl info once and then extract pieces with fallbacks to avoid
  // referencing constants that might be missing in some environments.
  $info = curl_getinfo($ch);

  // Request headers: prefer the CURLINFO_HEADER_OUT constant when present,
  // otherwise try common keys from curl_getinfo() array, or fallback to empty.
  $reqHeaderConst = defined('CURLINFO_HEADER_OUT') ? constant('CURLINFO_HEADER_OUT') : null;
  if ($reqHeaderConst !== null) {
    $request_headers = curl_getinfo($ch, $reqHeaderConst);
  } elseif (isset($info['request_header'])) {
    $request_headers = $info['request_header'];
  } else {
    $request_headers = '';
  }

  // Determine HTTPS by checking the effective URL first, then endpoint.
  $effectiveUrl = isset($info['url']) ? $info['url'] : $endpoint;
  $isHttps      = stripos($effectiveUrl, 'https://') === 0 || stripos($endpoint, 'https://') === 0;

  $header_size       = isset($info['header_size']) ? $info['header_size'] : 0;
  $http_status       = isset($info['http_code']) ? $info['http_code'] : 0;
  $http_status_valid = $http_status == 200 || $http_status == 201 || $http_status == 202 || $http_status == 204 || $http_status == 301 || $http_status == 302 || $http_status == 304;
  if ($response !== false) {
    $response_header = substr($response, 0, $header_size);
    $body            = substr($response, $header_size);
  } else {
    // If the response is false, set empty strings for headers and body
    $response_header = '';
    $body            = '';
  }
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
      'result'          => false,
      'latency'         => $latency,
      'duration'        => $end - $start,
      'executed_at'     => gmdate(DATE_ATOM, (int)$start),
      'error'           => $error_msg,
      'status'          => (string)$info['http_code'],
      'http_status_int' => (int)$http_status,
      'private'         => $isPrivate,
      'https'           => $isHttps,
      'anonymity'       => null,
      'curl_info'       => $info,
      'effective_url'   => isset($info['url']) ? $info['url'] : $endpoint,
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
      $result['result']  = false;
      $result['status']  = (string)$info['http_code'];
      $result['error']   = 'Private proxy ' . json_encode($matches);
      $result['private'] = true;
      $result['https']   = true;
      // private proxy always support HTTPS
      $result['anonymity'] = null;
    }
  }

  // if (empty($result['error'])) {
  //   if (!empty($body)) {
  //     $dom = \simplehtmldom\helper::str_get_html($body);
  //     echo "title: " . $dom->title() . PHP_EOL . PHP_EOL;
  //   }
  // }

  // capture curl info and close handle
  $result['curl_info']     = $info;
  $result['effective_url'] = isset($info['url']) ? $info['url'] : $endpoint;

  curl_close($ch);

  // Convert to milliseconds
  $latency  = round(($end - $start) * 1000);
  $duration = $end - $start;

  // result is empty = no error
  if (empty($result['error'])) {
    $result = array_merge($result, [
      'result'          => true,
      'latency'         => $latency,
      'duration'        => $duration,
      'executed_at'     => gmdate(DATE_ATOM, (int)$start),
      'error'           => null,
      'status'          => (string)$info['http_code'],
      'http_status_int' => (int)$http_status,
      'private'         => $isPrivate,
      'https'           => $isHttps,
      'anonymity'       => null,
      'curl_info'       => $info,
      'effective_url'   => isset($info['url']) ? $info['url'] : $endpoint,
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
function checkRawHeadersKeywords($input) {
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
function filterIpPortLines($inputFile) {
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
function clean_proxies_file($file) {
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
 * @param ProxyDB $proxy_db
 * @return array
 */
function parse_working_proxies($proxy_db) {
  // Retrieve working proxies from the provided ProxyDB object.
  // Limit the number of proxies fetched to avoid exhausting PHP memory on very large databases.
  // Caller can change the number by modifying this value if necessary.
  $working = $proxy_db->getWorkingProxies(5000);

  // Sort working proxies by the newest last_check column
  usort($working, function ($a, $b) {
    return strtotime($b['last_check']) - strtotime($a['last_check']);
  });

  // Map proxies data
  $array_mapper = array_map(function ($item) use ($proxy_db) {
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
      $proxy_db->updateData($item['proxy'], $item);
      // Re-fetch geolocation IP
      \PhpProxyHunter\GeoIpHelper::resolveGeoProxy($item['proxy']);
    }

    return $item;
  }, $working);

  // Format proxies data for working.txt file, separating each proxy by '|'
  $workingTxt = implode(PHP_EOL, array_map(function ($item) {
    return implode('|', $item);
  }, $array_mapper));

  $count = [
    'working'  => $proxy_db->countWorkingProxies(),
    'dead'     => $proxy_db->countDeadProxies(),
    'untested' => $proxy_db->countUntestedProxies(),
    'private'  => $proxy_db->countPrivateProxies(),
    'all'      => $proxy_db->countAllProxies(),
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
function writing_working_proxies_file($db, $lock_file = null) {
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
function extractIpPortFromFileCallback($filePath, $callback) {
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
function extractIpPortFromFile($filePath, $unique = false) {
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
function generateIPWithPorts($ip, $minPort = 10, $maxPort = 65535) {
  // Initialize an empty array to hold the IP:PORT values
  $ipPorts = [];

  // Loop from port 80 to the maximum port value
  for ($port = $minPort; $port <= $maxPort; $port++) {
    $ipPorts[] = $ip . ':' . $port;
  }

  return $ipPorts;
}
