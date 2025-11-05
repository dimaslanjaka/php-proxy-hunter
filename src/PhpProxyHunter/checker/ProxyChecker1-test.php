<?php

require_once __DIR__ . '/proxy-checker-1.php';

use PhpProxyHunter\Checker\ProxyChecker1;

$result = ProxyChecker1::check(new PhpProxyHunter\Checker\CheckerOptions([
  'proxy'     => '72.206.74.126:4145',
  'protocols' => ['http', 'socks4', 'socks5'],
  'timeout'   => 10,
  'verbose'   => true,
]));
var_dump($result);
