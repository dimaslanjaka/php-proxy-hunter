<?php

/**
 * Extract IP addresses from arbitrary text or JSON.
 *
 * @param string $text
 * @return array
 */
function extract_ips_from_text($text) {
  $ips = [];

  if (!is_string($text)) {
    return $ips;
  }

  $trim = trim($text);

  // Try JSON first
  $maybeJson = json_decode($trim, true);
  if (is_array($maybeJson)) {
    $iterator = function ($value) use (&$ips, &$iterator) {
      if (is_array($value)) {
        foreach ($value as $child) {
          $iterator($child);
        }
      } elseif (is_string($value)) {
        if (filter_var($value, FILTER_VALIDATE_IP)) {
          $ips[] = $value;
        }
      }
    };
    $iterator($maybeJson);
  }

  // IPv4 regex
  if (preg_match_all('/\b(?:(?:25[0-5]|2[0-4]\d|[01]?\d?\d)(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d?\d)){3})\b/', $text, $m)) {
    foreach ($m[0] as $ip) {
      $ips[] = $ip;
    }
  }

  // IPv6 rough extraction
  if (preg_match_all('/\b([0-9a-fA-F:]{2,})\b/', $text, $m6)) {
    foreach ($m6[1] as $cand) {
      if (filter_var($cand, FILTER_VALIDATE_IP)) {
        $ips[] = $cand;
      }
    }
  }

  return array_values(array_unique($ips));
}

/**
 * Parse headers from judge responses (raw or JSON).
 *
 * @param string $content
 * @return array
 */
function parse_headers_from_judge($content) {
  $headers = [];

  if (!is_string($content)) {
    return $headers;
  }

  // JSON?
  $maybeJson = json_decode($content, true);
  if (is_array($maybeJson)) {
    if (isset($maybeJson['headers']) && is_array($maybeJson['headers'])) {
      return $maybeJson['headers'];
    }
    return $maybeJson;
  }

  // Raw header parsing
  $lines = preg_split('/\r?\n/', $content);
  foreach ($lines as $line) {
    $parts = explode(':', $line, 2);
    if (count($parts) === 2) {
      $headers[trim($parts[0])] = trim($parts[1]);
    }
  }

  return $headers;
}

/**
 * Determine proxy anonymity: Transparent / Anonymous / Elite
 *
 * @param array       $ipInfos
 * @param array       $judgeInfos
 * @param string|null $deviceIp Injected for tests (optional)
 * @return string
 */
function parse_anonymity($ipInfos, $judgeInfos, $deviceIp = null) {
  // Always available — no need to check
  if ($deviceIp === null) {
    $deviceIp = getServerIp();
  }

  if (!$deviceIp) {
    return '';
  }

  // Collect proxy-reported IPs
  $reportedIpsMap = [];
  foreach ($ipInfos as $entry) {
    $content = isset($entry['content']) ? $entry['content'] : '';
    $ips     = extract_ips_from_text($content);
    foreach ($ips as $ip) {
      $reportedIpsMap[$ip] = true;
    }
  }
  $reportedIps = array_keys($reportedIpsMap);

  // Transparent if device IP appears anywhere
  if (in_array($deviceIp, $reportedIps, true)) {
    return 'Transparent';
  }

  // Judge header detection
  $privacyHeaders = [
    'VIA',
    'X-FORWARDED-FOR',
    'X-FORWARDED',
    'FORWARDED-FOR',
    'FORWARDED-FOR-IP',
    'FORWARDED',
    'CLIENT-IP',
    'PROXY-CONNECTION',
  ];

  $foundPrivacyHeader = false;

  foreach ($judgeInfos as $entry) {
    $content = isset($entry['content']) ? $entry['content'] : '';
    $headers = parse_headers_from_judge($content);

    foreach ($headers as $name => $value) {
      $upper = strtoupper(trim($name));

      if (in_array($upper, $privacyHeaders, true)) {
        $foundPrivacyHeader = true;
      }

      // Judge reveals real IP?
      $valueIps = extract_ips_from_text($value);
      if (in_array($deviceIp, $valueIps, true)) {
        return 'Transparent';
      }
    }
  }

  // If proxy IP seen and no real IP leaks
  if (!empty($reportedIps)) {
    return $foundPrivacyHeader ? 'Anonymous' : 'Elite';
  }

  // No reported IPs but privacy headers leaked
  if ($foundPrivacyHeader) {
    return 'Anonymous';
  }

  return 'Elite';
}

/**
 * Fetch real anonymity through curl.
 *
 * @param string      $proxy
 * @param string      $type
 * @param string|null $username
 * @param string|null $password
 * @return string
 */
function get_anonymity($proxy, $type, $username = null, $password = null) {
  $proxyJudges = [
    'https://wfuchs.de/azenv.php',
    'http://mojeip.net.pl/asdfa/azenv.php',
    'http://httpheader.net/azenv.php',
    'http://pascal.hoez.free.fr/azenv.php',
    'https://www.cooleasy.com/azenv.php',
    'https://httpbin.org/headers',
  ];

  $ipInfos = [
    'https://api.ipify.org/',
    'https://httpbin.org/ip',
    'https://cloudflare.com/cdn-cgi/trace',
  ];

  $judgeData = [];
  foreach ($proxyJudges as $url) {
    $ch      = buildCurl($proxy, $type, $url, [], $username, $password, 'GET', null, 0, 10, 10);
    $content = curl_exec($ch);
    curl_close($ch);

    $judgeData[] = [
      'url'     => $url,
      'content' => is_string($content) ? $content : '',
    ];
  }

  $ipData = [];
  foreach ($ipInfos as $url) {
    $ch      = buildCurl($proxy, $type, $url, [], $username, $password, 'GET', null, 0, 10, 10);
    $content = curl_exec($ch);
    curl_close($ch);

    $ipData[] = [
      'url'     => $url,
      'content' => is_string($content) ? $content : '',
    ];
  }

  return parse_anonymity($ipData, $judgeData);
}
