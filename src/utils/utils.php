<?php

/**
 * Retrieves the current caller information, with support for:
 * - Custom output format string (placeholders)
 * - Returning result as an array
 *
 * Supported placeholders for format:
 * - {class}    : Class name or [no-class]
 * - {function} : Method/function name or [no-function]
 * - {line}     : Line number or [no-line]
 * - {char}     : Approximate character position (1-based column)
 *
 * @param string $format Output format using placeholders (used only if $asArray is false)
 * @param bool $asArray Whether to return an associative array instead of formatted string
 * @return string|array Caller info in string format or as an associative array
 */
function getCallerInfo(string $format = '{class}-{function}-{line}-{char}', bool $asArray = false)
{
  $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
  $caller = isset($trace[1]) ? $trace[1] : $trace[0];

  $class = isset($caller['class']) ? $caller['class'] : '[no-class]';
  $function = isset($caller['function']) ? $caller['function'] : '[no-function]';
  $line = isset($caller['line']) ? $caller['line'] : '[no-line]';

  $charNo = 0;
  if (isset($caller['file'], $caller['line'])) {
    $fileLines = file($caller['file']);
    $lineContent = isset($fileLines[$caller['line'] - 1]) ? $fileLines[$caller['line'] - 1] : '';
    $charPos = strpos($lineContent, $function);
    $charNo = $charPos !== false ? $charPos + 1 : 0;
  }

  if ($asArray) {
    return [
      'class'    => $class,
      'function' => $function,
      'line'     => (int)$line,
      'char'     => $charNo,
    ];
  }

  return strtr($format, [
    '{class}'    => $class,
    '{function}' => $function,
    '{line}'     => (string)$line,
    '{char}'     => (string)$charNo,
  ]);
}
