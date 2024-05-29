<?php

require __DIR__ . '/func.php';

if (!$isCli) {
  if (!isset($_SESSION['admin']) || $_SESSION['admin'] === false)
    exit('Web server disallowed');
}

// Set the directory where you want to run git pull
$directory = __DIR__;

// Change to the directory
chdir($directory);

// Run git pull
$output = shell_exec('git pull');

// Output the result
echo $output;
