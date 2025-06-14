<?php

namespace PhpProxyHunter;

if (!function_exists('tmp')) {
  require_once __DIR__ . '/../../func.php';
}

/**
 * Class BaseController
 *
 * Serves as the base controller for the PhpProxyHunter framework.
 * Provides common functionality such as detecting CLI mode,
 * managing session IDs, and generating lock file paths.
 */
class BaseController
{
  /**
   * Indicates whether the script is running in CLI mode.
   */
  protected bool $isCLI;

  /**
   * Holds the session ID if available (for web requests only).
   */
  protected ?string $session_id = null;

  /**
   * The full path to the lock file associated with this controller.
   */
  protected string $lockFilePath;
  protected string $logFilePath;
  protected bool $isAdmin = false;

  /**
   * BaseController constructor.
   *
   * Detects whether the script is running in CLI mode,
   * initializes the session ID for web requests,
   * and sets a unique lock file path accordingly.
   */
  public function __construct()
  {
    $this->isCLI = php_sapi_name() === 'cli';

    if ($this->isCLI) {
      // CLI: use class name for lock file
      $this->lockFilePath = tmp() . '/runners/' . static::class . '.lock';
      $this->logFilePath = tmp() . '/logs/' . static::class . '.log';
    } else {
      if (session_status() === PHP_SESSION_ACTIVE) {
        $this->session_id = session_id();
        $this->lockFilePath = tmp() . '/runners/' . $this->session_id . '.lock';
        $this->logFilePath = tmp() . '/logs/' . $this->session_id . '.log';
        $this->isAdmin = isset($_SESSION['admin']) ? (bool)$_SESSION['admin'] : false;
      } else {
        // Fallback for non-CLI with no session
        $this->lockFilePath = tmp() . '/runners/' . static::class . '.web.lock';
        $this->logFilePath = tmp() . '/logs/' . static::class . '.web.log';
      }
    }
  }

  /**
   * Returns the lock file path for the current controller.
   */
  public function getLockFilePath(): string
  {
    return unixPath($this->lockFilePath);
  }

  protected function log(...$args): void
  {
    // Ensure stringify function is available
    if (!function_exists('stringify')) {
      require_once __DIR__ . '/../utils/string.php';
    }

    // Format the log message with a timestamp
    $timestamp = date('Y-m-d H:i:s');
    $message = implode(' ', array_map('stringify', $args));

    echo $message . PHP_EOL;

    // Append to log file
    append_content_with_lock($this->logFilePath, trim("[$timestamp] $message") . PHP_EOL);
  }
}

// Only run when executed directly from CLI, not when included or required
if (
  php_sapi_name() === 'cli' &&
  realpath(__FILE__) === realpath($_SERVER['argv'][0] ?? '')
) {
  $controller = new BaseController();
  echo 'Session ID: ' . ($controller->session_id ?? 'N/A') . PHP_EOL;
  echo 'Lock file path: ' . $controller->getLockFilePath() . PHP_EOL;
}
