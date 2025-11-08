<?php

namespace PhpProxyHunter\Checker;

class ProxyCheckerHttpOnly extends ProxyChecker {
  /**
   * Check the proxy for HTTP protocol.
   *
   * @param mixed $options
   * @return mixed
   * @throws \RuntimeException when not implemented
   */
  public static function check(CheckerOptions $options): CheckerResult {
    $result = new CheckerResult();

    // Ensure a proxy string was provided via options->proxy (host:port)
    if (empty($options->proxy)) {
      return $result;
    }

    $testUrl       = 'http://httpforever.com/';
    $expectedTitle = 'HTTP Forever';

    $protocols = isset($options->protocols) && is_array($options->protocols) && count($options->protocols) > 0
      ? $options->protocols
      : ['http', 'https', 'socks4', 'socks5', 'socks4a', 'socks5h'];

    $latencies = [];
    $http_ok   = false;

    foreach ($protocols as $protocol) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $testUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$options->timeout);
      curl_setopt($ch, CURLOPT_TIMEOUT, (int)$options->timeout + 5);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

      // Use the proxy provided in options
      curl_setopt($ch, CURLOPT_PROXY, $options->proxy);

      // Map protocol to CURLOPT_PROXYTYPE where applicable
      switch (strtolower($protocol)) {
        case 'socks4':
          curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
          break;
        case 'socks5':
          curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
          break;
        case 'socks4a':
          // PHP curl has no CURLPROXY_SOCKS4A constant; use SOCKS4 as fallback
          if (defined('CURLPROXY_SOCKS4A')) {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4A);
          } else {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
          }
          break;
        case 'socks5h':
          // socks5h: host name resolution through the proxy; fallback to SOCKS5
          if (defined('CURLPROXY_SOCKS5_HOSTNAME')) {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
          } else {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
          }
          break;
        case 'https':
        case 'http':
        default:
          // default is HTTP proxy
          // For HTTPS testing endpoint we still connect via proxy but request https if needed
          break;
      }

      // If protocol is https, override the URL scheme to https
      $urlToUse = $testUrl;
      if (strtolower($protocol) === 'https') {
        $urlToUse = preg_replace('#^http:#i', 'https:', $testUrl);
        curl_setopt($ch, CURLOPT_URL, $urlToUse);
        // enable SSL peer verification by default
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
      }

      // Optional auth
      if (!empty($options->username) || !empty($options->password)) {
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $options->username . ':' . $options->password);
      }

      // Provide a realistic browser user agent (matches check-http-proxy.php reference)
      curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0');

      $body = curl_exec($ch);
      $info = curl_getinfo($ch);

      $msgPrefix = strtoupper($protocol) . '://';

      if ($body !== false && isset($info['http_code']) && (int)$info['http_code'] === 200) {
        // record latency (ms)
        if (!empty($info['total_time'])) {
          $latencies[] = round($info['total_time'] * 1000, 2);
        }

        // Check raw headers for known dead markers (optional)
        if (!empty($body) && preg_match('/<title>(.*?)<\/title>/is', $body, $m)) {
          $title = trim($m[1]);
          if (strcasecmp($title, $expectedTitle) === 0) {
            $http_ok                = true;
            $result->isWorking      = true;
            $result->workingTypes[] = strtoupper($protocol);
            if (strtolower($protocol) === 'https') {
              $result->isSSL = true;
            }
          }
        }
      }

      curl_close($ch);

      // If we already found a valid http_ok, we can stop early
      if ($http_ok) {
        break;
      }
    }

    if (!empty($latencies)) {
      $result->latency = max($latencies);
    }

    return $result;
  }
}
