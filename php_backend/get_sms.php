<?php

require_once __DIR__ . '/../func.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header('Content-Type: text/plain; charset=utf-8');

// Get the 'sms' parameter from either POST or GET
$request = parsePostData(true);
$test = $request['sms'] ?? $_REQUEST['sms'];

// If 'sms' is empty, you can handle it accordingly
if (empty($test)) {
  // Handle the case when no message is provided
  echo 'No message provided' . PHP_EOL;
} else {
  echo "SMS received:\n\n{$test}";
}

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
$hash = getUserId();
if (!empty($request)) {
  write_file(tmp() . "/sms/get_sms_{$hash}.txt", json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
}

write_file(tmp() . "/sms/get_sms_{$hash}_post.txt", json_encode($_POST, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
write_file(tmp() . "/sms/get_sms_{$hash}_get.txt", json_encode($_GET, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
write_file(tmp() . "/sms/get_sms_{$hash}_request.txt", json_encode($_REQUEST, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
