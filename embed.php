<?php

require_once __DIR__ . '/func.php';

$forbidden = false;

// return 403 forbidden when captcha not resolved
if (!isset($_SESSION['captcha'])) {
    $forbidden = true;
} else {
    $last_check_captcha = $_SESSION['last_captcha_check'];

    // Convert RFC 3339 date string to a Unix timestamp
    $last_check_timestamp = strtotime($last_check_captcha);

    // Get the current Unix timestamp
    $current_timestamp = time();

    // Calculate the Unix timestamp for 1 hour ago
    $one_hour_ago = $current_timestamp - 3600;

    // Compare if the last check captcha was 1 hour ago
    if ($last_check_timestamp <= $one_hour_ago) {
        // The last check captcha was more than 1 hour ago
        $forbidden = true;
        unset($_SESSION['captcha']);
    }
}

if ($forbidden) {
    header("Content-type: application/json; charset=utf-8");
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

    // Check if the file extension is allowed (json or txt)
    if ($fileExtension === 'json' || $fileExtension === 'txt') {
        // Determine the Content-Type based on the file extension
        $contentType = mime_content_type($real_file);

        // Set the appropriate Content-Type header
        header("Content-Type: $contentType");

        // Read the file and echo its contents
        readfile($real_file);
    } else {
        // Invalid file type
        http_response_code(400);
        echo "Invalid file type. Only JSON and text files are allowed.";
    }
} else {
    // File not found or inaccessible
    http_response_code(404);
    echo "$file not found";
}
