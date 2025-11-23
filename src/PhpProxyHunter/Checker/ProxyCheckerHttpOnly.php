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
      : ['http', 'socks4', 'socks5', 'socks4a', 'socks5h'];

    $latencies = [];

    foreach ($protocols as $protocol) {
      $urlToUse = $testUrl;

      $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
      ];

      // Build cURL handle using helper
      $ch = \buildCurl($options->proxy, $protocol, $urlToUse, $headers, $options->username ?? null, $options->password ?? null, 'GET', null, 0);
      // override timeouts to match options
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$options->timeout);
      curl_setopt($ch, CURLOPT_TIMEOUT, (int)$options->timeout + 5);

      // if ($options->verbose) {
      //   echo sprintf("[CHECK] %s via %s\n", strtoupper($protocol), $urlToUse);
      // }

      $body    = curl_exec($ch);
      $info    = curl_getinfo($ch);
      $curlErr = curl_error($ch);

      $msg = sprintf('%s://%s ', strtolower($protocol), $options->proxy);

      if ($body !== false && isset($info['http_code']) && (int)$info['http_code'] === 200) {
        if (!empty($info['total_time'])) {
          $latencies[] = round($info['total_time'] * 1000, 2);
          $msg .= round($info['total_time'], 2) . 's ';
        }

        if (!empty($body) && preg_match('/<title>(.*?)<\/title>/is', $body, $m)) {
          $title        = trim($m[1]);
          $normTitle    = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($title))));
          $normExpected = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($expectedTitle))));
          if (mb_strtolower($normTitle) === mb_strtolower($normExpected)) {
            $result->isWorking      = true;
            $result->workingTypes[] = strtolower($protocol);
            $msg .= 'Title: ' . $title . ' (VALID)';
          } else {
            $msg .= 'Title: ' . $title . ' (INVALID)';
          }
        } else {
          $msg .= 'Title: N/A';
        }
      } else {
        $msg .= 'connection failure' . (!empty($curlErr) ? ' (' . $curlErr . ')' : '');
      }

      if ($options->verbose) {
        echo trim($msg) . PHP_EOL;
      }

      curl_close($ch);
    }

    if (!empty($latencies)) {
      $result->latency = max($latencies);
    }

    return $result;
  }
}
