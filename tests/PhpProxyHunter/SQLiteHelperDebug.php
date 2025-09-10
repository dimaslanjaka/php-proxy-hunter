<?php

require_once __DIR__ . '/../bootstrap.php';

use PhpProxyHunter\SQLiteHelper;

$table = 'test_table';
$db    = new SQLiteHelper(':memory:');
$db->createTable($table, [
  'id INTEGER PRIMARY KEY',
  'name TEXT',
  'age INTEGER',
]);

$col = 'to_drop';
$def = 'INTEGER';
if (!$db->columnExists($table, $col)) {
  $db->addColumnIfNotExists($table, $col, $def);
}
var_dump('column exists before', $db->columnExists($table, $col));
// Ensure all statements are finalized and unset before schema change
gc_collect_cycles(); // force collection of any lingering PDOStatement
$db->dropColumnIfExists($table, $col);
var_dump('column exists after', $db->columnExists($table, $col));
