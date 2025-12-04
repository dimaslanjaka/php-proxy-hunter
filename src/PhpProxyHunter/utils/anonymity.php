<?php

/**
 * Functions to determine proxy anonymity.
 */

/**
 * Obtain the anonymity of the proxy.
 *
 * @param string $response_ip_info The response containing IP information.
 * @param string $response_judges The response containing headers to judge anonymity.
 * @return string Anonymity level: Transparent, Anonymous, or Elite. Empty string on failure.
 */
function parse_anonymity($response_ip_info, $response_judges) {
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
function get_anonymity($proxy, $type, $username = null, $password = null) {
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
    $ch = buildCurl($proxy, $type, $url, [], $username, $password);
    $content = curl_exec($ch);
    curl_close($ch);
    if ($content !== false && is_string($content)) {
      return $content;
    }
    return '';
  }, $proxy_judges);
  $content_ip = array_map(function ($url) use ($proxy, $type, $username, $password) {
    $ch = buildCurl($proxy, $type, $url, [], $username, $password);
    $content = curl_exec($ch);
    curl_close($ch);
    if ($content !== false && is_string($content)) {
      return $content;
    }
    return '';
  }, $ip_infos);
  return parse_anonymity(implode("\n", $content_ip), implode("\n", $content_judges));
}
