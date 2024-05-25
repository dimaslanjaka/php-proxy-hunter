<?php

require_once __DIR__ . '/../func.php';

$files = getFilesByExtension(__DIR__ . '/../assets/proxies');
var_dump($files);
