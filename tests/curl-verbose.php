<?php

// Initialize a cURL session
$ch = curl_init();

// URL for the POST request
$url = 'http://httpbin.org/post';

// Data to be sent via POST
$postData = [
  'field1' => 'value1',
  'field2' => 'value2',
];

// Convert data array to a URL-encoded query string
$postFields = http_build_query($postData);

// Set the URL for the POST request
curl_setopt($ch, CURLOPT_URL, $url);

// Indicate that this is a POST request
curl_setopt($ch, CURLOPT_POST, true);

// Set the POST fields (data to send)
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

// Return the response instead of outputting it
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Enable verbose output
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Open a file to write the verbose output to
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// Execute the cURL request
$response = curl_exec($ch);

// Rewind the file pointer and read the verbose output
rewind($verbose);
$verboseLog = stream_get_contents($verbose);

// Close the file handle
fclose($verbose);

// Check for errors
if ($response === false) {
  echo 'Curl error: ' . curl_error($ch);
} else {
  // Decode the JSON response
  $data = json_decode($response, true);
  print_r($data);
}

// Close the cURL session
curl_close($ch);

// Output the verbose log
echo "Verbose information:\n", $verboseLog;
