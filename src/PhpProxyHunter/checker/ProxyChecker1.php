<?php

declare(strict_types=1);

namespace PhpProxyHunter\Checker;

use PhpProxyHunter\AnsiColors;

if (!function_exists('extractIPs')) {
  require_once __DIR__ . '/../utils/autoload.php';
}

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

    // --- Try SSL then Non-SSL ---
    foreach ([true, false] as $useSSL) {
      [$foundWorking, $isSSL, $workingTypes] = self::testProxyGroup(
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

      if ($foundWorking) {
        break;
      }
    }

    return new CheckerResult($foundWorking, $isSSL, $workingTypes);
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
  ): array {
    $foundWorking = false;
    $workingTypes = [];

    foreach ($protocols as $type) {
      $publicIP = getPublicIP(true, $timeout, [
        'proxy'    => $proxy,
        'type'     => $type,
        'username' => $username,
        'password' => $password,
      ], !$isSSL);

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
        break;
      }

      if ($ip !== $proxyIP && $ip !== $currentIp) {
        if ($debug) {
          self::log('yellow', "Proxy {$label} test succeeded for type {$type} (High anonymous, IP: {$ip}).");
        }
        $foundWorking   = true;
        $workingTypes[] = $type;
        break;
      }

      if ($debug) {
        self::log('red', "Proxy {$label} test failed for type {$type} (unexpected IP: {$ip}).");
      }
    }

    if (!$foundWorking && $debug) {
      self::log('red', "Proxy {$label} test failed for all types.");
    }

    return [$foundWorking, $isSSL, $workingTypes];
  }

  private static function log(string $color, string $message): void {
    echo AnsiColors::colorize([$color], $message) . PHP_EOL;
  }
}
