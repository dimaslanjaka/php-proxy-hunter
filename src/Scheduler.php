<?php

namespace PhpProxyHunter;

if (!defined('PHP_PROXY_HUNTER')) exit('access denied');

/** @noinspection PhpUnusedLocalVariableInspection */
$shutdown_functions = [];

class Scheduler
{
  public static function register(callable $func, ?string $identifier = null)
  {
    global $shutdown_functions;
    if (is_string($identifier) && !empty($identifier)) {
      $shutdown_functions[md5($identifier)] = $func;
    } else {
      $shutdown_functions[Scheduler::rand_str()] = $func;
    }
  }

  static function rand_str($length = 10): string
  {
    return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
  }

  public static function execute()
  {
    global $shutdown_functions;
    foreach ($shutdown_functions as $shutdown_function) {
      if (is_callable($shutdown_function)) {
        call_user_func($shutdown_function);
      }
    }
  }
}


register_shutdown_function(function () {
  Scheduler::execute();
});