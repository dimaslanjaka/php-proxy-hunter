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
function stringify($arg): string
{
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
 * Outputs content with appropriate Content-Type and UTF-8 charset.
 * Throws an exception if headers are already sent.
 *
 * @param mixed $data Content to output (array, object, string, int, float, bool, null)
 * @throws RuntimeException If headers are already sent
 * @return void
 */
function outputUtf8Content($data): void
{
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
