<?php

require_once __DIR__ . '/func-proxy.php';

// Check if the script is run from CLI
if (php_sapi_name() !== 'cli') {
  die("This script can only be run from the command line.");
}

// Parse command line arguments
$options = getopt("", ["url:"]);

if (!isset($options['url'])) {
  die("Usage: php send_curl.php --url=the_url\n");
}

$url = $options['url'];

// Define the path for the cookie file
$cookieFile = tmp() . '/cookies/default.txt';

// Create the directory if it doesn't exist
if (!file_exists(dirname($cookieFile))) {
  mkdir(dirname($cookieFile), 0777, true);
}

// Initialize cURL session
$ch = buildCurl(null, null, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile); // Save cookies to file
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile); // Use cookies from file

// Execute the request and get the response
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
  echo "cURL Error: " . curl_error($ch) . "\n";
} else {
  echo $response . "\n";
}

// Close cURL session
curl_close($ch);
