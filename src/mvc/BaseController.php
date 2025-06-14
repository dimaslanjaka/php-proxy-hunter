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
      $this->lockFilePath = unixPath(tmp() . '/runners/' . static::class . '.lock');
      $this->logFilePath = unixPath(tmp() . '/logs/' . static::class . '.log');
    } else {
      if (session_status() === PHP_SESSION_ACTIVE) {
        $this->session_id = session_id();
        $this->lockFilePath = unixPath(tmp() . '/runners/' . $this->session_id . '.lock');
        $this->logFilePath = unixPath(tmp() . '/logs/' . $this->session_id . '.log');
        $this->isAdmin = isset($_SESSION['admin']) ? (bool)$_SESSION['admin'] : false;
      } else {
        // Fallback for non-CLI with no session
        $this->lockFilePath = unixPath(tmp() . '/runners/' . static::class . '.web.lock');
        $this->logFilePath = unixPath(tmp() . '/logs/' . static::class . '.web.log');
      }
    }
  }

  /**
   * Retrieves the current URL details, including scheme, host, path, query,
   * parsed query parameters, full URL, and canonical URL.
   * Note: Fragment (hash) is not accessible from PHP.
   *
   * @return array{
   *     scheme: string,
   *     host: string,
   *     path: string,
   *     query: string|null,
   *     query_params: array<string, mixed>,
   *     fragment: null,
   *     full_url: string,
   *     canonical_url: string
   * }
   */
  public function getCurrentUrlInfo(): array
  {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    $scheme = $isHttps ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];
    $uri    = $_SERVER['REQUEST_URI']; // includes path + query

    $fullUrl = $scheme . '://' . $host . $uri;

    $parsed = parse_url($fullUrl);
    parse_str($parsed['query'] ?? '', $queryParams);

    return [
      'scheme'        => $scheme,
      'host'          => $host,
      'path'          => $parsed['path'] ?? '',
      'query'         => $parsed['query'] ?? null,
      'query_params'  => $queryParams,
      'fragment'      => null, // not available via PHP
      'full_url'      => $fullUrl,
      'canonical_url' => $scheme . '://' . $host . ($parsed['path'] ?? ''),
    ];
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
