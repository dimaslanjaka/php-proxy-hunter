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
   * @url /user/logs-get
   */
  public function logsGetAction()
  {
    if (!file_exists($this->logFilePath) || !is_readable($this->logFilePath)) {
      return ['status' => 'error', 'message' => 'Log file not found or not readable.'];
    }
    $content = @read_file($this->logFilePath);
    if ($content === false) {
      return 'Failed to read log file. File may be in use or locked.';
    }
    return $content;
  }

  /**
   * Clear user logs
   * @url /user/logs-clear
   */
  public function logsClearAction()
  {
    // Clear user logs
    if (file_exists($this->logFilePath)) {
      if (!@unlink($this->logFilePath)) {
        return ['status' => 'error', 'message' => 'Failed to clear logs. File may be in use or locked.'];
      }
    }
    return ['status' => 'success', 'message' => 'Logs cleared successfully.'];
  }
}
