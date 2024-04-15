<?php

require_once __DIR__ . '/func.php';

// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header('Content-Type: text/plain; charset=utf-8');

$filePath = __DIR__ . '/proxies.txt';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['proxies'])) {
    $ip_port = $_POST['proxies'];

    // Extract IP:PORT pairs into an array
    preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+)/', $ip_port, $matches);
    $ip_port_array = $matches[0];

    // Write IP:PORT pairs into proxy.txt file in append mode
    $file = fopen($filePath, 'a');
    foreach (array_unique($ip_port_array) as $ip_port) {
      if ($ip_port) fwrite($file, $ip_port . PHP_EOL);
    }
    fclose($file);

    $write = rewriteIpPortFile($filePath);

    $total = count($write);
    echo "IP:PORT pairs ($total) written to proxies.txt successfully.";
  } else {
    echo "IP:PORT data not found in POST request.";
  }
}
