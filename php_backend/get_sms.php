<?php

require_once __DIR__ . '/../func.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header('Content-Type: text/plain; charset=utf-8');
header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');

// Parse incoming data
$request = parsePostData(true);
$sms = $request['sms'] ?? $_REQUEST['sms'] ?? $_POST['sms'] ?? '';

if (empty($sms)) {
  echo "No message provided" . PHP_EOL;
} else {
  echo "SMS received:\n\n{$sms}";
}

// Date and log setup
$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$formattedTime = $now->format('Y-m-d\TH:i');
$logEntry = "{$formattedTime}: {$sms}";

// OTP detection
if (preg_match('/\b\d{4,8}\b/', $sms, $matches)) {
  $otp = "{$formattedTime}: {$matches[0]}";
  append_content_with_lock(tmp() . '/sms/get_sms_otp.txt', $otp . PHP_EOL);
}

// Log SMS
append_content_with_lock(tmp() . '/sms/get_sms.txt', $logEntry . PHP_EOL);

// Write logs per user
$hash = getUserId();
$basePath = tmp() . "/sms/{$hash}";

function json_pretty($data): string
{
  return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

write_file("{$basePath}.txt", json_pretty($request) . PHP_EOL);
write_file("{$basePath}_post.txt", json_pretty($_POST) . PHP_EOL);
write_file("{$basePath}_get.txt", json_pretty($_GET) . PHP_EOL);
write_file("{$basePath}_request.txt", json_pretty($_REQUEST) . PHP_EOL);
