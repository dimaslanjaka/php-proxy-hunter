<?php

require_once __DIR__ . '/../func.php';

$tmp   = __DIR__ . '/../tmp/tests';
$input = $tmp . '/input-removeStringFromFile.txt';
createParentFolders($input);
$str_to_remove = 'this will be deleted';
file_put_contents($input, implode(PHP_EOL, [
    $str_to_remove,
    $str_to_remove,
    randomWindowsUa(),
    randomWindowsUa(),
]));
$run = removeStringFromFile($input, $str_to_remove);
var_dump($run);
