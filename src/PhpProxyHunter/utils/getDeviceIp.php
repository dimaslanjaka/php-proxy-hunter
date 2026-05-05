<?php

/**
 * Return the public device IP (IPv4 or IPv6) or null on failure.
 *
 * This function uses the project's `executeCurl()` helper (from
 * `buildCurl.php`) to query well-known IP endpoints and returns the
 * first valid IP address it finds. It is tolerant of JSON or plain
 * text responses and validates the result using PHP's
 * `filter_var(..., FILTER_VALIDATE_IP)`.
 *
 * @param int $timeout Seconds to wait for each endpoint (default 5)
 * @param int $connect_timeout Seconds to wait for connect (default 5)
 * @return string|null IP as string, or null when unknown
 */
function getDeviceIp(int $timeout = 5, int $connect_timeout = 5): ?string {
  // endpoints that return caller IP in either plain text or JSON
  $endpoints = [
    'https://api.ipify.org?format=json',
    'https://api.ipify.org',
    'https://ifconfig.me/ip',
    'https://httpbin.org/ip',
    'https://ipinfo.io/ip',
  ];

  foreach ($endpoints as $endpoint) {
    // executeCurl signature: ($proxy, $type, $endpoint, $headers, $username, $password, $method, $post_data, $ssl, $connect_timeout, $timeout)
    $resp = null;
    try {
      $resp = executeCurl(null, 'http', $endpoint, [], null, null, 'GET', null, 0, $connect_timeout, $timeout);
    } catch (Throwable $e) {
      // ignore and try next endpoint
      continue;
    }

    if (!is_array($resp) || !isset($resp['result'])) {
      continue;
    }

    $body = trim((string)$resp['result']);
    if ($body === '') {
      continue;
    }

    // Try JSON decode (api.ipify / httpbin)
    if (strpos($body, '{') !== false) {
      $data = json_decode($body, true);
      if (is_array($data)) {
        if (isset($data['ip']) && filter_var($data['ip'], FILTER_VALIDATE_IP)) {
          return $data['ip'];
        }
        // some endpoints may return { "origin": "1.2.3.4" }
        if (isset($data['origin']) && filter_var($data['origin'], FILTER_VALIDATE_IP)) {
          return $data['origin'];
        }
      }
    }

    // plain text: try to find an IP inside the body
    // check for IPv4 or IPv6 using filter_var after extraction
    // first try entire body
    if (filter_var($body, FILTER_VALIDATE_IP)) {
      return $body;
    }

    // try to extract IPv4/IPv6 substring
    // IPv4
    if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $body, $m)) {
      $candidate = $m[0];
      if (filter_var($candidate, FILTER_VALIDATE_IP)) {
        return $candidate;
      }
    }
    // IPv6 (loose match)
    if (preg_match('/([0-9a-fA-F:]{2,})/', $body, $m6)) {
      $candidate6 = $m6[0];
      if (filter_var($candidate6, FILTER_VALIDATE_IP)) {
        return $candidate6;
      }
    }
  }

  return null;
}
