<?php

require_once __DIR__ . '/../func-proxy.php';

$tmp   = __DIR__ . '/../tmp/tests';
$input = $tmp . '/input-filterIpPortLines.txt';
createParentFolders($input);
$str_to_remove = 'this will be deleted';
file_put_contents($input, implode(PHP_EOL, [
    $str_to_remove,
    $str_to_remove,
    '213.251.185.168:27204',
    randomWindowsUa(),
    randomWindowsUa(),
]));

echo realpath($input) . PHP_EOL;

filterIpPortLines(realpath($input));
