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
