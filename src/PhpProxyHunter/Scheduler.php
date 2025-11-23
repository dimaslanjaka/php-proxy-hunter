<?php

/** @noinspection PhpUnusedLocalVariableInspection */

namespace PhpProxyHunter;

$shutdown_functions = [];

/**
 * Class Scheduler
 */
class Scheduler {
  public static $debug = true;

  /**
   * Registers a shutdown function.
   *
   * @param callable $func The shutdown function to register.
   * @param string|null $identifier Optional. An identifier for the function. If provided, the identifier will be used as the key in the shutdown functions array. If not provided, a random key will be generated.
   */
  public static function register($func, $identifier = null) {
    global $shutdown_functions;
    /** @noinspection RegExpRedundantEscape */
    $id = preg_replace('/[^\w\-\._\s]/u', '', $identifier);
    if (is_string($identifier) && !empty($identifier)) {
      $shutdown_functions[$id] = $func;
    } else {
      $shutdown_functions[self::randStr()] = $func;
    }
  }

  /**
   * Generates a random string.
   *
   * @param int $length The length of the random string to generate.
   * @return string The generated random string.
   */
  public static function randStr($length = 10) {
    return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
  }

  /**
   * Executes all registered shutdown functions.
   */
  public static function execute() {
    global $shutdown_functions;
    // Ensure $shutdown_functions is always an array
    if (!isset($shutdown_functions) || !is_array($shutdown_functions)) {
      $shutdown_functions = [];
    }
    // Sort the array by keys alphabetically
    ksort($shutdown_functions);
    foreach ($shutdown_functions as $key => $shutdown_function) {
      if (Scheduler::$debug) {
        echo "Scheduler: executing $key:" . PHP_EOL;
      }
      if (is_callable($shutdown_function)) {
        call_user_func($shutdown_function);
      }
    }
  }
}


register_shutdown_function(function () {
  Scheduler::execute();
});
