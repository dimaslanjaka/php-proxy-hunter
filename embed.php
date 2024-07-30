<?php

require_once __DIR__ . '/func.php';

$forbidden = false;

// Check if the X-UserToken header is set and not empty
if (!empty($_SERVER['HTTP_X_USER_TOKEN']) && !empty($_SERVER['HTTP_X_SERIAL_NUMBER'])) {
  $userToken = $_SERVER['HTTP_X_USER_TOKEN'];
  $serialNumber = $_SERVER['HTTP_X_SERIAL_NUMBER'];
  $userFile = __DIR__ . '/data/' . $userToken . '.json';
  if (file_exists($userFile)) {
    $read = read_file($userFile);
    if ($read) {
      $json = safe_json_decode($read);
      if ($json) {
        // Extract valid_until and convert to DateTime
        $validUntilStr = empty($json['valid_until']) ? '' : $json['valid_until'];
        $validUntil = DateTime::createFromFormat(DateTime::ATOM, $validUntilStr);

        if (!$validUntil) {
          // Invalid date format
          $forbidden = true;
        } else {
          // Get current date and time
          $now = new DateTime();
          $forbidden = $now > $validUntil;
          if (!$forbidden) {
            // Extract devices array
            $devices = empty($data['devices']) ? [] : $data['devices'];

            // if serial number exists in devices array
            // otherwise return 403
            $forbidden = in_array($serialNumber, $devices) == false;
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
    $last_check_captcha = $_SESSION['last_captcha_check'];

    // Convert RFC 3339 date string to a Unix timestamp
    $last_check_timestamp = strtotime($last_check_captcha);

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

$file = 'proxies.txt';

if (isset($_REQUEST['file'])) {
  $file = rawurldecode(trim($_REQUEST['file']));
}

$real_file = realpath(__DIR__ . '/' . $file);

if ($real_file && file_exists($real_file)) {
  // Determine the file extension
  $fileExtension = pathinfo($real_file, PATHINFO_EXTENSION);

  // Check if the file extension is allowed (json, txt, or log)
  if ($fileExtension === 'json' || $fileExtension === 'txt' || $fileExtension === 'log') {
    // Explicitly set the Content-Type based on the file extension
    if ($fileExtension === 'json') {
      $contentType = 'application/json';
    } else {
      $contentType = 'text/plain';
    }

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
