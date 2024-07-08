<?php

require __DIR__ . '/../func-proxy.php';

$proxy = '165.225.106.174:9443'; // gateway.zscalertwo.ne

$cek = checkProxy($proxy, 'http');
var_dump($cek);
