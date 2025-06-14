<?php

/**
 * Converts a value to a human-readable string.
 *
 * - Strings and numbers are cast to string.
 * - true becomes "true", false becomes "false".
 * - null becomes "null".
 * - Arrays and objects are JSON-encoded.
 *
 * @param mixed $arg The value to convert.
 * @return string The string representation.
 */
function stringify(mixed $arg): string
{
  return match (true) {
    is_string($arg), is_int($arg), is_float($arg) => (string) $arg,
    $arg === true  => 'true',
    $arg === false => 'false',
    is_null($arg)  => 'null',
    default        => json_encode($arg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
  };
}
