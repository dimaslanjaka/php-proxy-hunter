<?php

declare(strict_types=1);

namespace PhpProxyHunter\Checker;

use PhpProxyHunter\AnsiColors;

class ProxyChecker1 extends ProxyChecker {
  public static function check(CheckerOptions $options): CheckerResult {
    $proxy     = $options->proxy;
    $username  = $options->username;
    $password  = $options->password;
    $protocols = $options->protocols;
    $timeout   = $options->timeout ?? 10;
    $debug     = $options->verbose;

    $currentIp = getServerIp();
    if (empty($currentIp)) {
      return new CheckerResult(false, false, []);
    }

    $proxyIP      = extractIPs($proxy)[0] ?? null;
    $foundWorking = false;
    $isSSL        = false;
    $workingTypes = [];
    $anonymity    = '';
    $latency      = 0.0;

    // --- Try SSL then Non-SSL ---
    foreach ([true, false] as $useSSL) {
      $result = self::testProxyGroup(
        $protocols,
        $proxy,
        $username,
        $password,
        $currentIp,
        $proxyIP,
        $timeout,
        $debug,
        $useSSL
      );

      if ($result->isWorking) {
        return $result;
      }
    }

    return new CheckerResult(false, false, []);
  }

  /**
   * Test proxy across all protocols (either SSL or non-SSL)
   */
  private static function testProxyGroup(
    array $protocols,
    string $proxy,
    ?string $username,
    ?string $password,
    string $currentIp,
    ?string $proxyIP,
    int $timeout,
    bool $debug,
    bool $isSSL
  ): CheckerResult {
    $foundWorking   = false;
    $workingTypes   = [];
    $foundAnonymity = '';
    $foundLatency   = 0.0;

    foreach ($protocols as $type) {
      $tStart   = microtime(true);
      $publicIP = getPublicIP(true, $timeout, [
        'proxy'    => $proxy,
        'type'     => $type,
        'username' => $username,
        'password' => $password,
      ], !$isSSL);
      $tEnd = microtime(true);

      $foundLatency = round(($tEnd - $tStart) * 1000, 2); // ms

      $ip    = extractIPs($publicIP)[0] ?? null;
      $label = $isSSL ? 'SSL' : 'Non-SSL';

      if (empty($ip)) {
        if ($debug) {
          self::log('red', "Proxy {$label} test failed for type {$type} (no IP returned).");
        }
        continue;
      }

      if ($ip === $currentIp && $currentIp !== '127.0.0.1') {
        if ($debug) {
          self::log('red', "Proxy {$label} test failed for type {$type} (IP matches current IP).");
        }
        continue;
      }

      if ($ip === $proxyIP) {
        if ($debug) {
          self::log('green', "Proxy {$label} test succeeded for type {$type} (IP: {$ip}).");
        }
        $foundWorking   = true;
        $workingTypes[] = $type;
        $foundAnonymity = 'transparent';
        break;
      }

      if ($ip !== $proxyIP && $ip !== $currentIp) {
        if ($debug) {
          self::log('yellow', "Proxy {$label} test succeeded for type {$type} (High anonymous, IP: {$ip}).");
        }
        $foundWorking   = true;
        $workingTypes[] = $type;
        $foundAnonymity = 'elite';
        break;
      }

      if ($debug) {
        self::log('red', "Proxy {$label} test failed for type {$type} (unexpected IP: {$ip}).");
      }
    }

    if (!$foundWorking && $debug) {
      self::log('red', "Proxy {$label} test failed for all types.");
    }

    return new CheckerResult($foundWorking, $isSSL, $workingTypes, $foundAnonymity, $foundLatency);
  }

  private static function log(string $color, string $message): void {
    // Only colorize specific parts: label (SSL/Non-SSL), type, IP addresses, the word 'Proxy',
    // and success/fail keywords (succeeded/failed).
    // We'll scan the message and replace those tokens with colored versions.
    $colored = self::selectiveColorize($color, $message);
    echo $colored . PHP_EOL;
  }

  private static function selectiveColorize(string $color, string $message): string {
    // Patterns to colorize
    $patterns = [
      // Label: SSL or Non-SSL (word boundaries)
      '/\b(SSL|Non-SSL)\b/',
      // Type names (e.g., HTTP, HTTPS, SOCKS4, SOCKS5) - conservative: uppercase words or words with digits
      '/\b([A-Z0-9_-]{2,})\b/',
      // IP addresses (IPv4)
      '/\b(\d{1,3}(?:\.\d{1,3}){3})\b/',
      // The literal word 'Proxy'
      '/\b(Proxy)\b/i',
      // success/fail keywords
      '/\b(succeeded|failed)\b/i',
    ];

    // We'll iterate and replace matches with colored versions. To avoid recoloring already colored
    // segments, we build the output progressively.
    $offset = 0;
    $result = '';

    // Find all matches from all patterns with positions
    $matches = [];
    foreach ($patterns as $p) {
      if (preg_match_all($p, $message, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $match) {
          $matches[] = ['text' => $match[0], 'pos' => $match[1], 'len' => strlen($match[0])];
        }
      }
    }

    // Sort matches by position and remove overlaps (keep earlier match)
    usort($matches, fn ($a, $b) => $a['pos'] <=> $b['pos']);
    $filtered = [];
    $lastEnd  = -1;
    foreach ($matches as $m) {
      if ($m['pos'] >= $lastEnd) {
        $filtered[] = $m;
        $lastEnd    = $m['pos'] + $m['len'];
      }
    }

    foreach ($filtered as $m) {
      $pos = $m['pos'];
      $len = $m['len'];
      // append text before match
      if ($offset < $pos) {
        $result .= substr($message, $offset, $pos - $offset);
      }
      $token = substr($message, (int)$pos, (int)$len);
      // apply color
      $result .= AnsiColors::colorize([$color], $token);
      $offset = $pos + $len;
    }

    // append remainder
    if ($offset < strlen($message)) {
      $result .= substr($message, (int)$offset);
    }

    return $result;
  }
}
