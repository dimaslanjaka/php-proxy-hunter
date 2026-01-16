<?php

namespace PhpProxyHunter\Checker;

class ProxyCheckerGoogle extends ProxyChecker
{
  /**
   * Check the proxy by fetching Google homepage title across provided protocols.
   *
   * @param CheckerOptions $options
   * @return CheckerResult
   */
  public static function check(CheckerOptions $options): CheckerResult
  {
    $result = new CheckerResult();

    if (empty($options->proxy)) {
      return $result;
    }

    $url           = 'https://www.google.com/';
    $expectedTitle = 'Google';

    $protocols = isset($options->protocols) && is_array($options->protocols) && count($options->protocols) > 0
      ? $options->protocols
      : ['http', 'https', 'socks4', 'socks5', 'socks4a', 'socks5h'];

    $latencies = [];
    $found     = false;

    foreach ($protocols as $protocol) {
      $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
      ];
      $ch = \buildCurl($options->proxy, $protocol, $url, $headers, $options->username ?? null, $options->password ?? null, 'GET', null, 0);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$options->timeout);
      curl_setopt($ch, CURLOPT_TIMEOUT, (int)$options->timeout + 5);

      if ($options->verbose) {
        echo sprintf("[CHECK] %s via %s\n", strtoupper($protocol), $url);
      }

      $body    = curl_exec($ch);
      $info    = curl_getinfo($ch);
      $curlErr = curl_error($ch);

      $msg = sprintf('%s://%s ', strtoupper($protocol), $options->proxy);

      if ($body !== false && isset($info['http_code']) && (int)$info['http_code'] >= 200 && (int)$info['http_code'] < 400) {
        if (!empty($info['total_time'])) {
          $latencies[] = round($info['total_time'] * 1000, 2);
          $msg .= round($info['total_time'], 2) . 's ';
        }

        if (preg_match('/<title>(.*?)<\/title>/is', $body, $m)) {
          $title        = trim($m[1]);
          $normTitle    = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($title))));
          $normExpected = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($expectedTitle))));
          if (mb_strtolower($normTitle) === mb_strtolower($normExpected)) {
            $found                  = true;
            $result->isWorking      = true;
            $result->workingTypes[] = strtolower($protocol);
            if (strtolower($protocol) === 'https') {
              $result->isSSL = true;
            }
            $msg .= 'Title: ' . $title . ' (VALID)';
          } else {
            $msg .= 'Title: ' . $title . ' (INVALID)';
            // Check for private proxy titles
            if (PrivateProxyDetection::isPrivateProxyByTitle($title, $options->privateProxyTitlePatterns)) {
              $result->private = true;
            }
          }
        } else {
          $msg .= 'Title: N/A';
        }
      } else {
        $msg .= 'HTTP dead' . (!empty($curlErr) ? ' (' . $curlErr . ')' : '');
      }

      if ($options->verbose) {
        echo trim($msg) . PHP_EOL;
      }

      curl_close($ch);

      if ($found) {
        break;
      }
    }

    if (!empty($latencies)) {
      $result->latency = max($latencies);
    }

    return $result;
  }
}
