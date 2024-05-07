<?php

require_once __DIR__ . "/func-proxy.php";

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');
if (!$isCli)
  exit('web server access disallowed');

// clean all proxies
// merged into proxies-all.txt

$all = __DIR__ . '/proxies-all.txt';

// Define file paths array
$files = [
    __DIR__ . '/proxies.txt',
    __DIR__ . '/working.txt',
    __DIR__ . '/dead.txt'
];

setFilePermissions(array_merge($files, [$all]));

foreach ($files as $file) {
  echo "processing $file" . PHP_EOL;
  echo "remove lines not contains IP:PORT" . PHP_EOL;

  try {
    filterIpPortLines($file);
  } catch (InvalidArgumentException $e) {
    echo "Lines not containing IP:PORT format remove failed. " . $e->getMessage() . PHP_EOL;
  }

  echo "remove empty lines" . PHP_EOL;

  try {
    removeEmptyLinesFromFile($file);
  } catch (\Throwable $th) {
    echo 'Error fix bad contents from proxies.txt: ' . $th->getMessage() . PHP_EOL;
  }

  echo "fix file NUL" . PHP_EOL;

  try {
    fixFile($file);
  } catch (\Throwable $th) {
    echo 'Error fix bad contents from proxies.txt: ' . $th->getMessage() . PHP_EOL;
  }

  if (confirmAction("Are you want move $file content into $all:\t")) {
    $content = read_file($file);
    append_content_with_lock($all, $content);
  }
}


