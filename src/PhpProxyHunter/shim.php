<?php


if (!function_exists('json_validate')) {
  /**
   * Polyfill for PHP 8.3's json_validate().
   *
   * @param string $json  The JSON string to validate.
   * @param int    $depth The maximum depth (default 512).
   * @param int    $flags Bitmask of JSON decode options (default 0).
   * @return bool True if the string is valid JSON, false otherwise.
   */
  function json_validate(string $json, int $depth = 512, int $flags = 0): bool
  {
    json_decode($json, false, $depth, $flags);
    return (json_last_error() === JSON_ERROR_NONE);
  }
}
