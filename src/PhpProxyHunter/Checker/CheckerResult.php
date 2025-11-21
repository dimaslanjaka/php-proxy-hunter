<?php

namespace PhpProxyHunter\Checker;

/**
 * Represents the result of a proxy check.
 *
 * Contains flags indicating whether the proxy is working, whether it supports SSL,
 * and a list of working proxy types identified during the check.
 */
class CheckerResult {
  /**
   * Whether the proxy is working.
   *
   * @var bool
   */
  public $isWorking = false;

  /**
   * Whether the proxy supports SSL.
   *
   * @var bool
   */
  public $isSSL = false;

  /**
   * Anonymity level of the proxy (e.g. "transparent", "anonymous", "elite").
   *
   * @var string
   */
  public $anonymity = '';

  /**
   * Observed latency in milliseconds.
   *
   * @var float
   */
  public $latency = 0.0;

  /**
   * List of working proxy types (e.g. "HTTP", "SOCKS5").
   *
   * @var string[]
   */
  public $workingTypes = [];

  /**
   * Constructor.
   *
   * @param bool     $isWorking    True when the proxy is working.
   * @param bool     $isSSL        True when the proxy supports SSL.
   * @param string[] $workingTypes Array of working proxy type names.
   * @param string   $anonymity    Anonymity level name.
   * @param float    $latency      Observed latency in milliseconds.
   */
  public function __construct(bool $isWorking = false, bool $isSSL = false, array $workingTypes = [], string $anonymity = '', float $latency = 0.0) {
    $this->isWorking    = $isWorking;
    $this->isSSL        = $isSSL;
    $this->workingTypes = $workingTypes;
    $this->anonymity    = $anonymity;
    $this->latency      = $latency;
  }
}
