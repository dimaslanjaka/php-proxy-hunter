<?php

if (!function_exists('str_starts_with')) {
  /**
   * Checks if a string starts with a given prefix using regular expressions.
   *
   * @param string $haystack The input string.
   * @param string $needle The prefix to check for.
   * @return bool Returns true if the string starts with the prefix, false otherwise.
   */
  function str_starts_with(string $haystack, string $needle): bool
  {
    $pattern = '/^' . preg_quote($needle, '/') . '/';
    return (bool)preg_match($pattern, $haystack);
  }
}
