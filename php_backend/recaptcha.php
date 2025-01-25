<?php

require_once __DIR__ . '/../func.php';

global $isCli, $isAdmin;

if (!$isCli) {
  // Set CORS (Cross-Origin Resource Sharing) headers to allow requests from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");

  // Set content type to JSON with UTF-8 encoding
  header('Content-Type: application/json; charset=utf-8');

  // Ignore browser caching
  header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');

  // Check admin
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

if (!empty($_POST['g-recaptcha-response'])) {
  header('Content-Type: application/json; charset=utf-8');
  $secrets = [$_ENV['G_RECAPTCHA_SECRET'], $_ENV['G_RECAPTCHA_V2_SECRET']];

  foreach ($secrets as $secret) {
    $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $secret . '&response=' . $_POST['g-recaptcha-response']);
    $responseData = json_decode($verifyResponse);

    if ($responseData->success) {
      $_SESSION['captcha'] = true;
      $_SESSION['last_captcha_check'] = date(DATE_RFC3339);
      exit(json_encode(['message' => "Google reCAPTCHA verified successfully", "success" => true]));
    }
  }
}

echo json_encode(['error' => "Failed to verify Google reCAPTCHA", "success" => false]);
