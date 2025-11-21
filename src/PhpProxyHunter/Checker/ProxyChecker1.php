<?php

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

    $proxyIP = extractIPs($proxy)[0] ?? null;

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
   * Test a proxy across a group of protocol types and return the aggregated result.
   *
   * This method iterates over the provided protocol types, attempts to fetch the public IP
   * through the proxy (optionally using SSL), measures latency for each attempt, and determines
   * whether the proxy is working and its anonymity level.
   *
   * @param string[]    $protocols Array of protocol type identifiers (e.g. 'HTTP', 'HTTPS', 'SOCKS5').
   * @param string      $proxy     Proxy address (host[:port]).
   * @param string|null $username  Optional proxy username.
   * @param string|null $password  Optional proxy password.
   * @param string      $currentIp The current machine/public IP to compare against.
   * @param string|null $proxyIP   Optional extracted IP of the proxy itself.
   * @param int         $timeout   Request timeout in seconds.
   * @param bool        $debug     Whether to output debug logs.
   * @param bool        $isSSL     Whether to force SSL (labeling only; toggles how getPublicIP is called).
   *
   * @return CheckerResult Returns a CheckerResult with:
   *                        - isWorking: whether any protocol/type succeeded,
   *                        - isSSL: whether the successful attempt was SSL,
   *                        - workingTypes: list of protocol types that worked,
   *                        - anonymity: 'transparent'|'elite'|'' depending on result,
   *                        - latency: measured latency in milliseconds for the successful attempt (0.0 if none).
   */
  private static function testProxyGroup(
    array $protocols,
    $proxy,
    $username,
    $password,
    $currentIp,
    $proxyIP,
    $timeout,
    $debug,
    $isSSL
  ) {
    $foundWorking   = false;
    $workingTypes   = [];
    $foundAnonymity = '';
    $foundLatency   = 0.0;

    // Prioritize anonymity levels so if multiple types succeed we keep the "best" one
    $anonymityPriority = [
      'transparent' => 1,
      'anonymous'   => 2,
      'elite'       => 3,
    ];

    foreach ($protocols as $type) {
      $tStart   = microtime(true);
      $publicIP = getPublicIP(true, $timeout, [
        'proxy'    => $proxy,
        'type'     => $type,
        'username' => $username,
        'password' => $password,
      ], !$isSSL);
      $tEnd = microtime(true);

      $attemptLatency = round(($tEnd - $tStart) * 1000, 2);
      // ms

      $ip    = extractIPs($publicIP)[0] ?? null;
      $label = $isSSL ? 'SSL' : 'Non-SSL';

      if (empty($ip)) {
        if ($debug) {
          self::log('red', "Proxy {$label} test failed for type {$type} (no IP returned).");
        }
        continue;
      }

      // If the returned IP matches our current IP, the proxy leaked the client IP -> transparent
      // Treat transparent proxies as working (they forward traffic) but mark anonymity accordingly.
      if ($ip === $currentIp && $currentIp !== '127.0.0.1') {
        // Transparent proxy: it forwards traffic but leaks client IP.
        // Before accepting it as working, validate that the proxy can fetch a known HTTP endpoint.
        $testUrl = 'http://httpforever.com/';
        if ($debug) {
          self::log('yellow', "Proxy {$label} appears transparent for type {$type}; testing access to verification endpoint...");
        }

        // Build a cURL handle using same proxy settings
        $ch = buildCurl(
          $proxy,
          $type,
          $testUrl,
          ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0'],
          $username,
          $password
        );
        curl_setopt($ch, CURLOPT_TIMEOUT, max(5, (int)$timeout));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $out      = @curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($out !== false && $httpCode >= 200 && $httpCode < 400) {
          if ($debug) {
            self::log('green', "Proxy {$label} test succeeded for type {$type} (transparent but reachable).");
          }
          $foundWorking   = true;
          $workingTypes[] = strtolower($type);
          // choose best anonymity seen so far
          $newAnon = 'transparent';
          if ($foundAnonymity === '' || $anonymityPriority[$newAnon] > $anonymityPriority[$foundAnonymity]) {
            $foundAnonymity = $newAnon;
          }
          // record best (minimum) latency among successful attempts
          if ($foundLatency === 0.0 || $attemptLatency < $foundLatency) {
            $foundLatency = $attemptLatency;
          }
          // continue testing other types
          continue;
        } else {
          if ($debug) {
            self::log('red', "Proxy {$label} test failed for type {$type} (transparent but cannot reach verification endpoint).");
          }
          // treat as failed for this type and continue to next type
          continue;
        }
      }

      // If the returned IP matches the proxy IP, client's IP is hidden but destination sees proxy -> anonymous
      if ($ip === $proxyIP) {
        if ($debug) {
          self::log('green', "Proxy {$label} test succeeded for type {$type} (IP: {$ip} - anonymous).");
        }
        $foundWorking   = true;
        $workingTypes[] = strtolower($type);
        $newAnon        = 'anonymous';
        if ($foundAnonymity === '' || $anonymityPriority[$newAnon] > $anonymityPriority[$foundAnonymity]) {
          $foundAnonymity = $newAnon;
        }
        if ($foundLatency === 0.0 || $attemptLatency < $foundLatency) {
          $foundLatency = $attemptLatency;
        }
        // continue testing other types
        continue;
      }

      // If the returned IP is neither the proxy nor the client, it's likely an elite/high-anonymous proxy
      if ($ip !== $proxyIP && $ip !== $currentIp) {
        if ($debug) {
          self::log('yellow', "Proxy {$label} test succeeded for type {$type} (High anonymous, IP: {$ip}).");
        }
        $foundWorking   = true;
        $workingTypes[] = strtolower($type);
        $newAnon        = 'elite';
        if ($foundAnonymity === '' || $anonymityPriority[$newAnon] > $anonymityPriority[$foundAnonymity]) {
          $foundAnonymity = $newAnon;
        }
        if ($foundLatency === 0.0 || $attemptLatency < $foundLatency) {
          $foundLatency = $attemptLatency;
        }
        // continue testing other types
        continue;
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

  /**
   * Output a colored log message.
   *
   * @param string $color Color name for formatting.
   * @param string $message Message to log.
   * @return void
   */
  private static function log(string $color, string $message) {
    // Primary color is kept for whole message fallback. selectiveColorize will apply
    // token-level colors (label, IPs, types, Proxy word, succeeded/failed).
    $colored = self::selectiveColorize($color, $message);
    echo $colored . PHP_EOL;
  }

  private static function selectiveColorize(string $color, string $message): string {
    // We'll detect token types and apply specific colors for each token class.
    // Priority: Label (SSL/Non-SSL) -> IP -> success/fail -> Proxy -> Type
    // Define regexes with named token types for easier handling.
    $tokenPatterns = [
      'label'  => '/\b(SSL|Non-SSL)\b/',
      'ip'     => '/\b(\d{1,3}(?:\.\d{1,3}){3})\b/',
      'status' => '/\b(succeeded|failed)\b/i',
      'proxy'  => '/\b(Proxy)\b/i',
      // Type names (HTTP, HTTPS, SOCKS4, SOCKS5, etc.) - uppercase words or digits
      'type' => '/\b([A-Z0-9_-]{2,})\b/',
    ];

    // Color map per token type. Use AnsiColors supported names.
    $colorMap = [
      'label'  => ['green', 'bold'],   // SSL default (Non-SSL handled below)
      'ip'     => ['cyan'],            // IP addresses
      'status' => ['green', 'bold'],   // succeeded (we'll switch to red if 'failed')
      'proxy'  => ['red', 'bold'],     // the word 'Proxy'
      'type'   => ['yellow'],          // protocol type
    ];

    // Gather matches from all patterns with positions and types
    $matches = [];
    foreach ($tokenPatterns as $type => $pat) {
      if (preg_match_all($pat, $message, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $match) {
          $matches[] = [
            'text' => $match[0],
            'pos'  => $match[1],
            'len'  => strlen($match[0]),
            'type' => $type,
          ];
        }
      }
    }

    // Sort matches by position and remove overlaps (keep earlier match)
    usort($matches, function ($a, $b) {
      if ($a['pos'] == $b['pos']) {
        return 0;
      }
      return ($a['pos'] < $b['pos']) ? -1 : 1;
    });
    $filtered = [];
    $lastEnd  = -1;
    foreach ($matches as $m) {
      if ($m['pos'] >= $lastEnd) {
        $filtered[] = $m;
        $lastEnd    = $m['pos'] + $m['len'];
      }
    }

    // Build result progressively
    $offset = 0;
    $result = '';
    foreach ($filtered as $m) {
      $pos  = $m['pos'];
      $len  = $m['len'];
      $type = $m['type'];

      if ($offset < $pos) {
        $result .= substr($message, $offset, $pos - $offset);
      }
      $token = substr($message, (int)$pos, (int)$len);

      // Choose color for token type; allow dynamic adjustment for status token
      $fmt = $colorMap[$type] ?? [$color];
      if ($type === 'status') {
        // If the token is 'failed' (case-insensitive), use red instead
        if (preg_match('/failed/i', $token)) {
          $fmt = ['red', 'bold'];
        } else {
          $fmt = ['green', 'bold'];
        }
      }

      // For label, differentiate Non-SSL vs SSL: Non-SSL -> yellow, SSL -> green
      if ($type === 'label') {
        if (stripos($token, 'non-ssl') !== false) {
          $fmt = ['yellow', 'bold'];
        } else {
          $fmt = ['green', 'bold'];
        }
      }

      $result .= AnsiColors::colorize($fmt, $token);
      $offset = $pos + $len;
    }

    // append remainder
    if ($offset < strlen($message)) {
      $result .= substr($message, (int)$offset);
    }

    // If nothing was colored (no matches), fallback to coloring entire message with provided color
    if ($result === '') {
      return AnsiColors::colorize([$color], $message);
    }

    return $result;
  }
}
