<?php

declare(strict_types=1);

namespace PhpProxyHunter\Checker;

class CheckerOptions
{
  public bool $verbose    = false;
  public int $timeout     = 10;
  public array $protocols = ['http', 'https', 'socks4', 'socks5'];
  public string $username = '';
  public string $password = '';
  public string $proxy    = '';
  public function __construct(array $options = [])
  {
    foreach ($options as $key => $value) {
      $this->$key = $value;
    }
  }
}
