<?php

declare(strict_types=1);

namespace PhpProxyHunter\Checker;

/**
 * Configuration options for the proxy checker.
 *
 * Instances hold runtime options that control how proxy checks are performed.
 * Options may be supplied to the constructor as an associative array where keys
 * match the public property names and values replace the defaults.
 */
class CheckerOptions
{
  /**
   * Enable verbose output for debugging.
   *
   * @var bool
   */
  public bool $verbose = false;

  /**
   * Connection timeout in seconds.
   *
   * @var int
   */
  public int $timeout = 10;

  /**
   * Protocols to try when checking a proxy (e.g. 'http', 'https', 'socks4', 'socks5').
   *
   * @var string[]
   */
  public array $protocols = ['http', 'https', 'socks4', 'socks5'];

  /**
   * Optional username for authenticated proxies.
   *
   * @var string
   */
  public string $username = '';

  /**
   * Optional password for authenticated proxies.
   *
   * @var string
   */
  public string $password = '';

  /**
   * Fallback proxy to use for outgoing connections (host:port).
   *
   * @var string
   */
  public string $proxy = '';

  /**
   * Constructor.
   *
   * Accepts an associative array of options to override defaults. Only keys
   * matching public property names will be assigned.
   *
   * @param array<string,mixed> $options Associative array of option overrides.
   */
  public function __construct(array $options = [])
  {
    foreach ($options as $key => $value) {
      $this->$key = $value;
    }
  }
}
