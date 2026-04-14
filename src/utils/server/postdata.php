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
 * Parse CLI args into key/value pairs.
 *
 * Supported forms:
 * - --key=value
 * - key=value
 * - --flag (stored as true)
 *
 * @param array<int, string> $args CLI arguments (usually $argv).
 *
 * @return array Parsed CLI arguments.
 */
function parseCliArgs(array $args): array {
  $result = [];

  foreach ($args as $index => $arg) {
    // Skip script name in argv[0].
    if ($index === 0) {
      continue;
    }

    if (!is_string($arg) || trim($arg) === '') {
      continue;
    }

    $normalized = ltrim($arg, '-');

    if (strpos($normalized, '=') !== false) {
      [$key, $value] = explode('=', $normalized, 2);
      $key           = trim($key);

      if ($key !== '') {
        $result[$key] = $value;
      }

      continue;
    }

    if (strpos($arg, '--') === 0 && $normalized !== '') {
      $result[$normalized] = true;
    }
  }

  return $result;
}

/**
 * Get request parameters from HTTP request data or CLI arguments.
 *
 * @return array The request parameters array.
 */
function parseQueryOrPostBody(): array {
  global $isCli;
  if (!$isCli) {
    return array_merge(parsePostData(true), $_REQUEST, $_GET, $_POST);
  }

  $argv = isset($GLOBALS['argv']) && is_array($GLOBALS['argv'])
    ? $GLOBALS['argv']
    : (is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : []);

  return parseCliArgs($argv);
}
