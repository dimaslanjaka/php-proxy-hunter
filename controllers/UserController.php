<?php

if (!function_exists('tmp')) {
  require_once __DIR__ . '/autoload.php';
  require_once __DIR__ . '/../func-proxy.php';
}

use PhpProxyHunter\BaseController;

class UserController extends BaseController
{
  public function indexAction()
  {
    // Return a list of users (placeholder)
    return ['status' => 'success', 'users' => []];
  }

  /**
   * Get user logs
   * @url /user/logs
   */
  public function logsAction()
  {
    // Return user logs (placeholder)
    return read_file($this->logFilePath);
  }


  /**
   * Clear user logs
   * @url /user/logs-clear
   */
  public function logsClearAction()
  {
    // Clear user logs
    if (file_exists($this->logFilePath)) {
      unlink($this->logFilePath);
    }
    return ['status' => 'success', 'message' => 'Logs cleared successfully.'];
  }
}
