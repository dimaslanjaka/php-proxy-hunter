<?php

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
    foreach ($ip_port_array as $ip_port) {
      fwrite($file, $ip_port . PHP_EOL);
    }
    fclose($file);

    rewriteIpPortFile($filePath);

    echo "IP:PORT pairs written to proxies.txt successfully.";
  } else {
    echo "IP:PORT data not found in POST request.";
  }
}

/**
 * Function to extract IP:PORT combinations from a text file and rewrite the file with only IP:PORT combinations.
 *
 * @param string $filename The path to the text file.
 * @return void
 */
function rewriteIpPortFile($filename)
{
  $ipPortList = array();

  // Open the file for reading
  $file = fopen($filename, "r");

  // Read each line from the file and extract IP:PORT combinations
  while (!feof($file)) {
    $line = fgets($file);

    // Match IP:PORT pattern using regular expression
    preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+)/', $line, $matches);

    // Add matched IP:PORT combinations to the list
    foreach ($matches[0] as $match) {
      $ipPortList[] = $match;
    }
  }

  // Close the file
  fclose($file);

  // Open the file for writing (truncate existing content)
  $file = fopen($filename, "w");

  // Write extracted IP:PORT combinations to the file
  foreach ($ipPortList as $ipPort) {
    fwrite($file, $ipPort . "\n");
  }

  // Close the file
  fclose($file);
}
