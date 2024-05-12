<?php

namespace PhpProxyHunter;

use Exception;

class Session
{
  private static $_instance = null;
  public $sessionCookieName = 'uf';
  public $cookiePath = '/';
  public $cookieDomain = '';
  private $path_defined = null;
  /**
   * Cookie will only be set if a secure HTTPS connection exists.
   *
   * @var bool
   */
  private $cookieSecure = false;
  /**
   * sid regex expression.
   *
   * @var string
   */
  private $sidRegexp;

  /**
   * @throws Exception
   */
  public function __construct($timeout = 3600, $session_folder = null)
  {
    if (!empty($session_folder) && !file_exists($session_folder)) mkdir($session_folder, 755, true);
    if (!$this->is_session_started()) {
      //$this->configure($timeout, $session_folder);
      //$this->start_timeout($timeout);
      $this->handle($timeout, $session_folder);
    }
  }

  public function is_session_started(): bool
  {
    return PHP_SESSION_ACTIVE == session_status();
  }

  /**
   * @param int $timeout Session timeout in seconds.
   * @param string|null $folder Optional session folder path.
   * @throws Exception If unable to create session folder.
   */
  public function handle(int $timeout, ?string $folder = null): void
  {
    $name = '_' . $timeout . md5(Server::getRequestIP() . Server::useragent());
    if (empty(trim($folder))) {
      $folder = __DIR__ . '/../tmp/sessions';
      if (!file_exists($folder)) {
        if (!mkdir($folder, 0755, true)) {
          throw new Exception('Unable to create session folder.');
        }
      }
    }
    session_save_path($folder);

    session_set_cookie_params($timeout);

    ini_set('session.gc_maxlifetime', $timeout);
    ini_set('session.cookie_lifetime', $timeout);
    ini_set('session.gc_probability', 100);
    ini_set('session.gc_divisor', 100);

    session_id($name);

    // Ensure strict session mode is disabled before setting session name
    ini_set('session.use_strict_mode', 0);
    session_name($name);

    // Now enable strict session mode
    ini_set('session.use_strict_mode', 1);

    $handler = new FileSessionHandler($folder, 'PHP_PROXY_HUNTER');
    session_set_save_handler(
        [$handler, 'open'],
        [$handler, 'close'],
        [$handler, 'read'],
        [$handler, 'write'],
        [$handler, 'destroy'],
        [$handler, 'gc']
    );

    register_shutdown_function('session_write_close');
    session_start();

    $path = ini_get('session.save_path');
    if (!file_exists($path . '/.htaccess')) {
      file_put_contents($path . '/.htaccess', 'deny from all');
    }

    if (!isset($_SESSION['session_started'])) {
      $_SESSION['session_started'] = $this->now();
      $_SESSION['session_timeout'] = $timeout;
      $_SESSION['cookie_timeout'] = $timeout;
    }
  }

  /**
   * @throws Exception
   */
  public function now(): \DateTime
  {
    return new \DateTime(null, new \DateTimeZone('Asia/Jakarta'));
  }
}