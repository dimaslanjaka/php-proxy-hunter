<?php

// === PHP 8 SHIMS for deprecated/removed functions ===

if (!function_exists('json_validate')) {
  /**
   * Polyfill for PHP 8.3's json_validate().
   *
   * @param string $json  The JSON string to validate.
   * @param int    $depth The maximum depth (default 512).
   * @param int    $flags Bitmask of JSON decode options (default 0).
   * @return bool True if the string is valid JSON, false otherwise.
   */
  function json_validate(string $json, int $depth = 512, int $flags = 0): bool {
    json_decode($json, false, $depth, $flags);
    return (json_last_error() === JSON_ERROR_NONE);
  }
}

// get_magic_quotes_gpc() was removed in PHP 8
if (!function_exists('get_magic_quotes_gpc')) {
  function get_magic_quotes_gpc(): int {
    return 0;
  }
}

// get_magic_quotes_runtime() was removed in PHP 8
if (!function_exists('get_magic_quotes_runtime')) {
  function get_magic_quotes_runtime(): int {
    return 0;
  }
}

// set_magic_quotes_runtime() was removed in PHP 8
if (!function_exists('set_magic_quotes_runtime')) {
  function set_magic_quotes_runtime($new_setting): bool {
    return false;
  }
}

// create_function() removed in PHP 8
if (!function_exists('create_function')) {
  function create_function($args, $code) {
    return eval('return function(' . $args . ') {' . $code . '};');
  }
}

// each() removed in PHP 8
if (!function_exists('each')) {
  function each(&$array) {
    $key = key($array);
    if ($key === null) {
      return false;
    }
    $value = current($array);
    next($array);
    return [
      1       => $value,
      'value' => $value,
      0       => $key,
      'key'   => $key,
    ];
  }
}

// get_html_translation_table() behavior changed
if (!function_exists('get_html_translation_table_compat')) {
  function get_html_translation_table_compat($table = HTML_SPECIALCHARS, $flags = ENT_COMPAT, $encoding = 'UTF-8') {
    return get_html_translation_table($table, $flags | ENT_SUBSTITUTE, $encoding);
  }
}
