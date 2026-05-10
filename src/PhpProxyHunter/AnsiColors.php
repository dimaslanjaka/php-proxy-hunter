<?php

namespace PhpProxyHunter;

class AnsiColors
{
  /**
   * Colorize text with ANSI codes.
   *
   * Accepts `$format` as an array of format names (e.g. ['red','underline'])
   * or as a string (e.g. 'red,underline' or 'red underline'). For convenience
   * the method also detects and swaps arguments when callers provide the
   * `$text` first and the `$format` second.
   *
   * @param array|string $format Format names or comma/space separated string.
   * @param string|array $text   Text to colorize, or an array when args are swapped.
   * @return string
   */
  public static function colorize($format = [], $text = '') {
    $codes = [
      'bold'          => 1,
      'italic'        => 3,
      'underline'     => 4,
      'strikethrough' => 9,
      'black'         => 30,
      'red'           => 31,
      'green'         => 32,
      'yellow'        => 33,
      'blue'          => 34,
      'magenta'       => 35,
      'cyan'          => 36,
      'white'         => 37,
      'blackbg'       => 40,
      'redbg'         => 41,
      'greenbg'       => 42,
      'yellowbg'      => 44,
      'bluebg'        => 44,
      'magentabg'     => 45,
      'cyanbg'        => 46,
      'lightgreybg'   => 47,
    ];
    // Allow callers to pass arguments in either order: colorize($format, $text)
    // or the common mistaken usage colorize($text, $format).
    if ((!is_array($format) && !empty($format)) || is_string($format)) {
      // Normalize when the second argument looks like a format (color names)
      $maybeFormat = is_array($text) ? $text : [$text];
      $allAreCodes = true;
      foreach ($maybeFormat as $m) {
        if (!isset($codes[$m])) {
          $allAreCodes = false;
          break;
        }
      }
      if ($allAreCodes) {
        $tmp    = $format;
        $format = $text;
        $text   = $tmp;
      }
    }

    // Normalize format to array (accept strings like 'red' or 'red,underline')
    if (is_string($format)) {
      $format = preg_split('/[,|\s]+/', trim($format));
    }

    $formatMap = array_map(function ($v) use ($codes) {
      return isset($codes[$v]) ? $codes[$v] : null;
    }, is_array($format) ? $format : []);
    $formatMap = array_values(array_filter($formatMap, function ($v) {
      return $v !== null && $v !== '';
    }));
    if (empty($formatMap)) {
      return $text;
    }
    return "\033[" . implode(';', $formatMap) . 'm' . $text . "\033[0m";
  }

  /**
   * Convert ANSI colored text to HTML.
   *
   * @param string $ansiText
   * @return string
   */
  public static function ansiToHtml($ansiText) {
    // Map ANSI codes to CSS styles
    $ansiMap = [
      1  => 'font-weight:bold;',
      3  => 'font-style:italic;',
      4  => 'text-decoration:underline;',
      9  => 'text-decoration:line-through;',
      30 => 'color:black;',
      31 => 'color:red;',
      32 => 'color:green;',
      33 => 'color:yellow;',
      34 => 'color:blue;',
      35 => 'color:magenta;',
      36 => 'color:cyan;',
      37 => 'color:white;',
      40 => 'background:black;',
      41 => 'background:red;',
      42 => 'background:green;',
      44 => 'background:yellow;',
      45 => 'background:magenta;',
      46 => 'background:cyan;',
      47 => 'background:lightgrey;',
    ];

    // Regex to match \033[...m ... \033[0m (with multiple codes)
    $pattern = '/\033\[([0-9;]+)m(.*?)\033\[0m/s';
    $html    = $ansiText;
    while (preg_match($pattern, $html, $matches)) {
      $codes  = explode(';', $matches[1]);
      $styles = '';
      foreach ($codes as $code) {
        if (isset($ansiMap[(int)$code])) {
          $styles .= $ansiMap[(int)$code];
        }
      }
      $replacement = '<span style="' . $styles . '">' . $matches[2] . '</span>';
      $html        = preg_replace($pattern, $replacement, $html, 1);
    }
    // Remove any remaining ANSI codes
    $html = preg_replace('/\033\[[0-9;]*m/', '', $html);
    return $html;
  }

  /**
   * Remove ANSI escape sequences from a string.
   *
   * @param string $text
   * @return string
   */
  public static function removeAnsi($text) {
    if (!is_string($text) || $text === '') {
      return $text;
    }

    // Remove common SGR sequences (e.g. "\033[31m")
    $clean = preg_replace('/\033\[[0-9;]*m/', '', $text);
    if ($clean === null) {
      return $text;
    }

    // Remove any other CSI sequences that may remain
    $clean = preg_replace('/\033\[[0-9;]*[A-Za-z]/', '', $clean);
    if ($clean === null) {
      return $text;
    }

    return $clean;
  }

  /**
   * Color meter (0–100) from green → yellow → red.
   * If $inverse = true, direction becomes red → yellow → green.
   *
   * @param int $value 0–100
   * @param bool $inverse reverse gradient
   * @return string colored value
   */
  public static function meter($value, $inverse = false) {
    $value = max(0, min(100, $value));

    // Normalize (0–1)
    $t = $value / 100;

    if ($inverse) {
      $t = 1 - $t;
    }

    // ANSI color mapping (simple 3-step gradient)
    if ($t < 0.5) {
      // green → yellow
      $color = ($t < 0.25) ? 'green' : 'yellow';
    } else {
      // yellow → red
      $color = 'red';
    }

    return self::colorize($color, $value . '%');
  }
}
