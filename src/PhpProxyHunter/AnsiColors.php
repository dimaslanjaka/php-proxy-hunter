<?php

namespace PhpProxyHunter;

class AnsiColors {
  /**
   * Colorize text with ANSI codes.
   *
   * @param array $format
   * @param string $text
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
    $formatMap = array_map(function ($v) use ($codes) {
      return isset($codes[$v]) ? $codes[$v] : null;
    }, $format);
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
}
