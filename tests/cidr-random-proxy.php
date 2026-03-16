<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\CIDR;

// Example CIDR range
$cidr = '192.168.1.0/24';

// Generate a random IP address within the CIDR range
$randomIP = CIDR::generateRandomIP($cidr);

// Generate a random port number
$randomPort = CIDR::generateRandomPort();

// Output the random IP:Port combination
echo "Random IP:Port: $randomIP:$randomPort\n";
