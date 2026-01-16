<?php

namespace PhpProxyHunter\Checker;

class PrivateProxyDetection
{
  private static $privateProxyTitlePatterns = [
    '/^Access to Mozilla is restricted$/i',
    '/^Private Proxy Server$/i',
    '/^Proxy Server Access Denied$/i',
    '/Portal Page/i',
    '/Router Login/i',
    '/SecuwaySSL/i',
  ];

  public static function isPrivateProxyByTitle(string $title, array $privateProxyTitlePatterns): bool
  {
    foreach (array_merge(self::$privateProxyTitlePatterns, $privateProxyTitlePatterns) as $pattern) {
      if (preg_match($pattern, $title)) {
        return true;
      }
    }
    return false;
  }
}
