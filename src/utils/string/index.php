<?php

/**
 * Converts a value to a human-readable string.
 *
 * - string, int, float → cast to string
 * - true → "true"
 * - false → "false"
 * - null → "null"
 * - array/object → JSON-encoded
 *
 * @param mixed $arg The value to convert.
 * @return string The string representation.
 */
function stringify($arg) {
  if (is_string($arg) || is_int($arg) || is_float($arg)) {
    return (string) $arg;
  } elseif ($arg === true) {
    return 'true';
  } elseif ($arg === false) {
    return 'false';
  } elseif (is_null($arg)) {
    return 'null';
  }

  return json_encode($arg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Converts a string or an array of strings into regex patterns.
 *
 * @param string|array $input The input string or array of strings.
 * @return string|array The regex pattern(s) corresponding to the input.
 */
function string_to_regex($input) {
  // If $input is an array, process each string
  if (is_array($input)) {
    return array_map(function ($string) {
      return '/\b' . preg_quote($string, '/') . '\b/';
    }, $input);
  } else { // If $input is a single string, process it
    return '/\b' . preg_quote($input, '/') . '\b/';
  }
}

/**
 * Outputs content with appropriate Content-Type and UTF-8 charset.
 * Throws an exception if headers are already sent.
 *
 * @param mixed $data Content to output (array, object, string, int, float, bool, null)
 * @throws RuntimeException If headers are already sent
 * @return void
 */
function outputUtf8Content($data) {
  if (headers_sent($file, $line)) {
    throw new RuntimeException("Cannot set headers. Headers already sent in $file on line $line.");
  }

  if (is_array($data) || is_object($data)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  } elseif (is_string($data)) {
    if ($data !== strip_tags($data)) {
      header('Content-Type: text/html; charset=utf-8');
    } else {
      header('Content-Type: text/plain; charset=utf-8');
    }
    echo $data;
  } elseif (is_int($data) || is_float($data)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo (string) $data;
  } elseif (is_bool($data)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo $data ? 'true' : 'false';
  } elseif (is_null($data)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'null';
  } else {
    header('Content-Type: text/plain; charset=utf-8');
    echo '[Unsupported content type: ' . gettype($data) . ']';
  }
}

function generateRandomString($length = 10) {
  $characters   = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[rand(0, strlen($characters) - 1)];
  }
  return $randomString;
}

/**
 * Split a string into an array of lines. Support CRLF
 *
 * @param string|null $str The input string to split.
 * @return array An array of lines, or an empty array if the split fails.
 */
function split_by_line($str) {
  if (!$str) {
    return [];
  }

  $lines = preg_split('/\r?\n/', $str);

  // Check if preg_split succeeded
  if ($lines === false) {
    return [];
  }

  return $lines;
}

/**
 * Anonymize an email address by masking the username.
 *
 * @param string|null $email The email address to anonymize.
 * @return string The anonymized email address.
 */
function anonymizeEmail($email) {
  // Return same value when empty
  if (empty($email)) {
    return $email;
  }

  // Split the email into username and domain
  list($username, $domain) = explode('@', $email);

  // Anonymize the username (keep only the first and the last character)
  $username_anon = substr($username, 0, 1) . str_repeat('*', strlen($username) - 2) . substr($username, -1);

  // Reconstruct the anonymized email
  return $username_anon . '@' . $domain;
}
