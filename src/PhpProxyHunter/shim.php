<?php

if (!function_exists('json_validate')) {
  /**
   * Validates if a string is valid JSON.
   *
   * @param string $string The string to validate.
   * @return bool True if the string is valid JSON, false otherwise.
   */
  function json_validate($string)
  {
    json_decode($string);
    return (json_last_error() === JSON_ERROR_NONE);
  }
}
