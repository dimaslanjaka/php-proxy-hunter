<?php

require_once __DIR__ . '/../func-proxy.php';

\PhpProxyHunter\Scheduler::register(function () {
  echo 'Hello world' . PHP_EOL;
});

exit(0);

print('No response');
