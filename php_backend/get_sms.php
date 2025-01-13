<?php

require_once __DIR__ . '/../func.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header('Content-Type: text/plain; charset=utf-8');

// Get the 'sms' parameter from either POST or GET
$request = parsePostData(true);
$test = isset($request['sms']) ? $request['sms'] : '';

// If 'sms' is empty, you can handle it accordingly
if (empty($test)) {
  // Handle the case when no message is provided
  die('No message provided');
} else {
  echo "SMS received:\n\n{$test}";
}

// Sanitize the input to prevent harmful content
// htmlspecialchars will encode special characters (like <, >, &)
$test = htmlspecialchars($test, ENT_QUOTES, 'UTF-8');

// Set timezone and get current date and time
$timezone = new DateTimeZone('Asia/Jakarta');
$date = new DateTime('now', $timezone);

// Format the current date for folder and log naming
$today = $date->format('d-m-Y H:i:s');
$month = $date->format('F');
$todayDate = $date->format('d-m-Y');
$todayOrder = $date->format('Y-m-d');
$formattedDateTime = $date->format('Y-m-d\TH:i');

// Prepare log content
$content = "{$formattedDateTime} {$test}";

// Write to the file
write_file(tmp() . '/sms/get_sms.txt', $content . PHP_EOL);
if ($request) {
  $hash = getUserId();
  write_file(tmp() . "/sms/get_sms_{$hash}.json", json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
}
