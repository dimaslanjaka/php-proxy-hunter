<?php

require_once __DIR__ . '/../func.php';

/**
 * Google reCAPTCHA verification endpoint.
 *
 * Handles CORS, session, and reCAPTCHA validation for API requests.
 *
 * @author dimaslanjaka
 */

// Detect CLI mode
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
  // Set CORS headers
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: *');
  header('Access-Control-Allow-Methods: *');

  // Set content type to JSON with UTF-8 encoding
  header('Content-Type: application/json; charset=utf-8');

  // Ignore browser caching
  header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
}

$request = parseQueryOrPostBody();

// Status check endpoint
if (isset($request['status'])) {
  header('Content-Type: application/json; charset=utf-8');
  $verified = !empty($_SESSION['captcha']) && $_SESSION['captcha'] === true;
  $response = $verified
    ? ['message' => 'Captcha already verified', 'success' => true]
    : ['error' => 'Captcha not verified', 'success' => false];
  echo json_encode($response);
  exit;
}

// reCAPTCHA verification
if (!empty($request['g-recaptcha-response'])) {
  header('Content-Type: application/json; charset=utf-8');
  $secrets = array_filter([
    $_ENV['G_RECAPTCHA_SECRET']    ?? null,
    $_ENV['G_RECAPTCHA_V2_SECRET'] ?? null,
  ]);

  if (empty($secrets)) {
    echo json_encode(['error' => 'reCAPTCHA secret key not configured', 'success' => false]);
    exit;
  }

  foreach ($secrets as $secret) {
    $verifyUrl = sprintf(
      'https://www.google.com/recaptcha/api/siteverify?secret=%s&response=%s',
      urlencode($secret),
      urlencode($request['g-recaptcha-response'])
    );
    $verifyResponse = file_get_contents($verifyUrl);
    $responseData   = json_decode($verifyResponse);

    if (!empty($responseData) && !empty($responseData->success) && $responseData->success) {
      $_SESSION['captcha']            = true;
      $_SESSION['last_captcha_check'] = date(DATE_RFC3339);
      echo json_encode(['message' => 'Google reCAPTCHA verified successfully', 'success' => true]);
      exit;
    }
  }
}

echo json_encode(['error' => 'Failed to verify Google reCAPTCHA', 'success' => false]);
