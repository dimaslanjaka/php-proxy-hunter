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
    } else {
      if (session_status() === PHP_SESSION_ACTIVE) {
        $this->session_id = session_id();
        $this->lockFilePath = tmp() . '/runners/' . $this->session_id . '.lock';
      } else {
        // Fallback for non-CLI with no session
        $this->lockFilePath = tmp() . '/runners/' . static::class . '.web.lock';
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
