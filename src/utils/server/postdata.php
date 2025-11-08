<?php

/**
 * Parse incoming POST request data based on Content-Type.
 *
 * @param bool $detect_get Whether to return `$_GET` data if the content type is unsupported (default is false).
 *
 * @return array The parsed POST data.
 */
function parsePostData(bool $detect_get = false): ?array {
  // Initialize an empty array to store the parsed data
  $result = [];

  // Get the Content-Type header of the request
  $contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';

  if (strpos($contentType, 'multipart/form-data') !== false) {
    // Merge POST fields into the result
    $result = array_merge($result, $_POST);

    // Add uploaded files to the result
    foreach ($_FILES as $key => $file) {
      $result[$key] = $file;
    }
  } elseif (strpos($contentType, 'application/json') !== false) {
    // Decode the JSON from the input stream
    $json_data = json_decode(file_get_contents('php://input'), true);

    if (is_array($json_data)) {
      $result = array_merge($result, $json_data);
    }
  } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
    // For URL-encoded form data, $_POST already contains the parsed data
    $result = array_merge($result, $_POST);
  }

  return $detect_get ? array_merge($_GET, $result) : $result;
}

/**
 * Get the request parameters either from POST data, GET parameters, or a combination.
 *
 * @return array The request parameters array.
 */
function parseQueryOrPostBody(): array {
  global $isCli;
  if (!$isCli) {
    return array_merge(parsePostData(true), $_REQUEST, $_GET, $_POST);
  }
  return [];
}
