<?php

require_once __DIR__ . '/../func.php';

$tmp    = __DIR__ . '/../tmp/tests';
$input  = $tmp . '/input-removeStringAndMoveToFile.txt';
$output = $tmp . '/output-removeStringAndMoveToFile.txt';
createParentFolders($input);
file_put_contents($output, '');
$str_to_remove = 'this will be deleted';
file_put_contents($input, implode(PHP_EOL, [
    $str_to_remove,
    randomWindowsUa(),
    randomWindowsUa(),
]));
$run = removeStringAndMoveToFile($input, $output, $str_to_remove);
var_dump($run);
