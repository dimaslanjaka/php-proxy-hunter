<?php

require_once __DIR__ . '/../func.php';

$proxy = '37.19.220.179:8443';

$check = isPortOpen($proxy);

echo "$proxy is open $check";
