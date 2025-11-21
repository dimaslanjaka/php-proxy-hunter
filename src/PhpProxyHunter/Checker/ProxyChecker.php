<?php

namespace PhpProxyHunter\Checker;

abstract class ProxyChecker {
  /**
   * Check a proxy using a single CheckerOptions object.
   *
   * @param CheckerOptions $options Options containing proxy, auth and protocols to test
   * @return CheckerResult Result object containing details about the check
   */
  abstract public static function check(CheckerOptions $options): CheckerResult;
}
