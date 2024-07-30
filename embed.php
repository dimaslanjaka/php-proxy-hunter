<?php

require_once __DIR__ . '/func.php';

$forbidden = false;

// Check if the X-User-Token and X-Serial-Number headers are set
$userToken = isset($_SERVER['HTTP_X_USER_TOKEN']) ? $_SERVER['HTTP_X_USER_TOKEN'] : null;
$serialNumber = isset($_SERVER['HTTP_X_SERIAL_NUMBER']) ? $_SERVER['HTTP_X_SERIAL_NUMBER'] : null;

if ($userToken && $serialNumber) {
  $userFile = __DIR__ . '/data/' . $userToken . '.json';
  if (file_exists($userFile)) {
    $read = read_file($userFile);
    if ($read !== false) {
      $json = safe_json_decode($read);
      if ($json) {
        $validUntilStr = isset($json['valid_until']) ? $json['valid_until'] : '';
        $validUntil = DateTime::createFromFormat(DateTime::ATOM, $validUntilStr);

        if ($validUntil === false) {
          // Invalid date format
          $forbidden = true;
        } else {
          // Get current date and time
          $now = new DateTime();
          $forbidden = $now > $validUntil;

          if (!$forbidden) {
            // Extract devices array
            $devices = isset($json['devices']) ? $json['devices'] : [];

            // If serial number exists in devices array, allow access; otherwise, return 403
            $forbidden = !in_array($serialNumber, $devices, true);
          }
        }
      }
    }
  }
} else {
  // Check if the captcha session is empty
  if (empty($_SESSION['captcha'])) {
    // Return 403 forbidden when captcha is not resolved
    $forbidden = true;
  } else {
    $last_check_captcha = isset($_SESSION['last_captcha_check']) ? $_SESSION['last_captcha_check'] : '';

    // Convert RFC 3339 date string to a Unix timestamp
    $last_check_timestamp = strtotime($last_check_captcha) ?: 0;

    // Get the current Unix timestamp
    $current_timestamp = time();

    // Calculate the Unix timestamp for 1 hour ago
    $one_hour_ago = $current_timestamp - 3600;

    // Compare if the last check captcha was more than 1 hour ago
    if ($last_check_timestamp <= $one_hour_ago) {
      // The last check captcha was more than 1 hour ago
      $forbidden = true;
      unset($_SESSION['captcha']);
    }
  }
}

if ($forbidden) {
  header("Content-Type: application/json; charset=utf-8");
  http_response_code(403);
  exit(json_encode(['error' => 'unauthorized, try login first']));
}

$file = isset($_REQUEST['file']) ? rawurldecode(trim($_REQUEST['file'])) : 'proxies.txt';
$real_file = realpath(__DIR__ . '/' . $file);

if ($real_file && file_exists($real_file)) {
  // Determine the file extension
  $fileExtension = pathinfo($real_file, PATHINFO_EXTENSION);

  // Check if the file extension is allowed (json, txt, or log)
  if (in_array($fileExtension, ['json', 'txt', 'log'], true)) {
    // Explicitly set the Content-Type based on the file extension
    $contentType = ($fileExtension === 'json') ? 'application/json' : 'text/plain';

    // Set the appropriate Content-Type header
    header("Content-Type: $contentType");

    // Read the file and echo its contents
    readfile($real_file);
  } else {
    // Invalid file type
    http_response_code(400);
    echo "Invalid file type. Only JSON, text, and log files are allowed.";
  }
} else {
  // File not found or inaccessible
  http_response_code(404);
  echo "$file not found";
}
