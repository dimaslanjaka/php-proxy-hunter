<?php

require_once __DIR__ . '/../func.php';

global $isAdmin;

// === Headers ===
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header("Content-Type: text/plain; charset=utf-8");
header("Expires: Sun, 01 Jan 2014 00:00:00 GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");

// === Helpers ===
function json_pretty($data): string
{
  return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// === Define common log paths ===
$tmpDir = tmp() . '/sms';
$logFile       = "{$tmpDir}/get_sms.txt";
$otpLogFile    = "{$tmpDir}/get_sms_otp.txt";

// === Admin Mode: Show SMS Log ===
if ($isAdmin && isset($_GET['read'])) {
  echo file_exists($logFile)
    ? "--- SMS Log ---\n" . file_get_contents($logFile)
    : "--- SMS Log not found ---\n";
  exit;
}

// === Parse incoming data ===
$request = parsePostData(true);
$sms = $request['sms'] ?? $_REQUEST['sms'] ?? $_POST['sms'] ?? '';

echo empty($sms)
  ? "No message provided" . PHP_EOL
  : "SMS received:\n\n{$sms}";

// === Time & log formatting ===
$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$timestamp = $now->format('Y-m-d\TH:i');
$logEntry = "{$timestamp}: {$sms}";

// === OTP Extraction ===
if (preg_match('/\b\d{4,8}\b/', $sms, $matches)) {
  append_content_with_lock($otpLogFile, "{$timestamp}: {$matches[0]}" . PHP_EOL);
}

// === Log SMS ===
append_content_with_lock($logFile, $logEntry . PHP_EOL);

// === Per-user request logging ===
$hash = getUserId();
$base = "{$tmpDir}/{$hash}";

write_file("{$base}.txt", json_pretty($request) . PHP_EOL);
write_file("{$base}_post.txt", json_pretty($_POST)   . PHP_EOL);
write_file("{$base}_get.txt", json_pretty($_GET)    . PHP_EOL);
write_file("{$base}_request.txt", json_pretty($_REQUEST) . PHP_EOL);
