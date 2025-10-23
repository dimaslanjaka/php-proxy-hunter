<?php

require_once __DIR__ . '/../proxy_hunter/utils/config.php';

use ProxyHunter\Utils\ConfigDB;

// Sample usage for proxy_hunter/utils/config.php
echo "ConfigDB sample usage\n";

$dbPath = __DIR__ . '/test_config.db';
@unlink($dbPath);

$db = new ConfigDB('sqlite', ['db_path' => $dbPath]);

// store an associative array
$db->set('foo', ['bar' => 42, 'baz' => 'hello']);
$val = $db->get('foo');
echo "get('foo') => ";
var_export($val);
echo PHP_EOL;

// store an stdClass
$obj    = new stdClass();
$obj->x = 7;
$obj->y = 'z';
$db->set('obj', $obj);
// retrieve as decoded array
$decoded = $db->get('obj');
echo "get('obj') => ";
var_export($decoded);
echo PHP_EOL;
// retrieve and map to stdClass via modelClass
$asObj = $db->get('obj', 'stdClass');
echo "get('obj' as stdClass) => ";
var_export($asObj);
echo PHP_EOL;

// store a simple string
$db->set('simple', 'a string');
echo "get('simple') => ";
var_export($db->get('simple'));
echo PHP_EOL;

// delete a key
$db->delete('simple');
echo 'after delete, simple => ';
var_export($db->get('simple'));
echo PHP_EOL;

$db->close();
@unlink($dbPath);
