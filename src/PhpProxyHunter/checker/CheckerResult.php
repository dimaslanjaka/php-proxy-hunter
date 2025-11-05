<?php

declare(strict_types=1);

namespace PhpProxyHunter\Checker;

/**
 * Represents the result of a proxy check.
 *
 * Contains flags indicating whether the proxy is working, whether it supports SSL,
 * and a list of working proxy types identified during the check.
 */
class CheckerResult
{
  /**
   * Whether the proxy is working.
   *
   * @var bool
   */
  public bool $isWorking = false;

  /**
   * Whether the proxy supports SSL.
   *
   * @var bool
   */
  public bool $isSSL = false;

  /**
   * List of working proxy types (e.g. "HTTP", "SOCKS5").
   *
   * @var string[]
   */
  public array $workingTypes = [];

  /**
   * Constructor.
   *
   * @param bool     $isWorking    True when the proxy is working.
   * @param bool     $isSSL        True when the proxy supports SSL.
   * @param string[] $workingTypes Array of working proxy type names.
   */
  public function __construct(bool $isWorking = false, bool $isSSL = false, array $workingTypes = [])
  {
    $this->isWorking    = $isWorking;
    $this->isSSL        = $isSSL;
    $this->workingTypes = $workingTypes;
  }
}
