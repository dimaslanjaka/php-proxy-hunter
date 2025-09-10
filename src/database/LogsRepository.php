<?php

use PhpProxyHunter\CoreDB;

class LogsRepository
{
  private CoreDB $db;

  public function __construct(CoreDB $db)
  {
    $this->db = $db;
  }

  /**
   * Retrieves the log content by its hash.
   *
   * @param string $hash The hash identifying the log file.
   * @return string|null The log content if found, or null if not found.
   * @throws \Exception If required helper functions are not defined.
   */
  public function getLogsByHash(string $hash): ?string
  {
    if (!function_exists('tmp')) {
      throw new \Exception('Function tmp() is not defined. Make sure to include the necessary files.');
    }
    if (!function_exists('read_file')) {
      throw new \Exception('Function read_file() is not defined. Make sure to include the necessary files.');
    }
    $file = tmp() . '/logs/' . $hash . '.txt';
    if (!file_exists($file)) {
      $file = tmp() . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $hash . '.txt';
    }
    return read_file($file) ?: null;
  }
}
