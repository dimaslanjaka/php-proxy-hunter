<?php

require __DIR__ . '/../func-proxy.php';

$ch = buildCurl(null, null, 'https://www.google.com');
$response = curl_exec($ch);
var_dump($response);
