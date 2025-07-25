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
   * Parse incoming POST request data based on Content-Type.
   *
   * @param bool $detect_get Whether to return `$_GET` data if the content type is unsupported (default is false).
   *
   * @return array The parsed POST data.
   */
  public function parsePostData(bool $detect_get = false): ?array
  {
    // Initialize an empty array to store the parsed data
    $result = [];

    // Get the Content-Type header of the request
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

    if (strpos($contentType, "multipart/form-data") !== false) {
      // Merge POST fields into the result
      $result = array_merge($result, $_POST);

      // Add uploaded files to the result
      foreach ($_FILES as $key => $file) {
        $result[$key] = $file;
      }
    } elseif (strpos($contentType, "application/json") !== false) {
      // Decode the JSON from the input stream
      $json_data = json_decode(file_get_contents('php://input'), true);

      if (is_array($json_data)) {
        $result = array_merge($result, $json_data);
      }
    } elseif (strpos($contentType, "application/x-www-form-urlencoded") !== false) {
      // For URL-encoded form data, $_POST already contains the parsed data
      $result = array_merge($result, $_POST);
    }

    return $detect_get ? array_merge($_GET, $result) : $result;
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

  /**
   * Logs one or more messages with a timestamp.
   *
   * This method stringifies all provided arguments, concatenates them into a single message,
   * and outputs the message to the standard output. It also appends the message, prefixed
   * with a timestamp, to a log file specified by $this->logFilePath. The log file is written
   * to using a locking mechanism to prevent concurrent write issues.
   *
   * @param mixed ...$args One or more values to be logged. Each value will be stringified.
   * @return void
   */
  public function log(...$args): void
  {
    // Ensure stringify function is available
    if (!function_exists('stringify')) {
      require_once __DIR__ . '/../utils/string.php';
    }

    // Format the log message with a timestamp
    $timestamp = date('Y-m-d H:i:s');
    $message = implode(' ', array_map('stringify', $args));

    foreach ($args as $arg) {
      if (is_array($arg) || is_object($arg)) {
        print_r($arg);
      } else {
        echo $arg;
      }
      echo PHP_EOL;
    }

    // Append to log file
    append_content_with_lock($this->logFilePath, trim("[$timestamp] $message") . PHP_EOL);
  }

  /**
   * Executes a shell command in the background and logs its output.
   *
   * Generates a runner script (batch or shell) to execute the given command,
   * redirects output to a log file, and stores the process ID in the lock file.
   *
   * @param string $cmd The shell command to execute.
   * @return array{runner: string, command: string} Information about the runner script and command.
   */
  protected function executeCommand($cmd)
  {
    // Build the command to run in the background, redirecting output and storing PID
    $cmd = sprintf(
      "%s >> %s 2>&1 & echo $! >> %s",
      $cmd,
      escapeshellarg($this->logFilePath),
      escapeshellarg($this->lockFilePath)
    );

    // Determine script extension based on OS
    $ext = (strtoupper(PHP_OS_FAMILY) === 'WINDOWS') ? '.bat' : '.sh';
    $runner = tmp() . "/runners/" . basename($this->lockFilePath, '.lock') . $ext;

    // Write the command to the runner script file
    write_file($runner, $cmd);

    // Execute the runner script in the background
    runBashOrBatch($runner, [], getCallerInfo() . '-' . $this->session_id);

    return ['runner' => $runner, 'command' => $cmd];
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
