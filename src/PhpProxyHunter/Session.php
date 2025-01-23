<?php

namespace PhpProxyHunter;

use DateTime;
use DateTimeZone;
use Exception;

class Session
{
  private $session_prefix_name = "PHP_PROXY_HUNTER";

  /**
   * Session constructor that starts a session with a specified timeout and optional session folder.
   *
   * @param int $timeout Session timeout in seconds.
   * @param string|null $session_folder Optional custom folder for storing session files.
   * @throws Exception If session folder creation fails or session cannot be started.
   */
  public function __construct(int $timeout, $session_folder = null)
  {
    if (!empty($session_folder) && !file_exists($session_folder)) {
      mkdir($session_folder, 755, true);
    }
    if (!$this->is_session_started()) {
      $name = md5($this->session_prefix_name . $timeout . Server::getRequestIP() . Server::useragent());
      if (empty(trim($session_folder))) {
        $session_folder = __DIR__ . '/../../tmp/sessions';
        if (!file_exists($session_folder)) {
          if (!mkdir($session_folder, 0755, true)) {
            throw new Exception('Unable to create session folder.');
          }
        }
      }

      // set sessions folder permission
      chmod($session_folder, 0777);
      session_save_path($session_folder);
      session_set_cookie_params($timeout);
      ini_set('session.gc_maxlifetime', $timeout);
      ini_set('session.cookie_lifetime', $timeout);
      ini_set('session.gc_probability', 100);
      ini_set('session.gc_divisor', 100);
      session_id($name);
      session_start();
      $path = ini_get('session.save_path');
      if (!file_exists($path . '/.htaccess')) {
        file_put_contents($path . '/.htaccess', 'deny from all');
      }
      if (!isset($_SESSION['session_started'])) {
        $_SESSION['session_started'] = $this->now();
        $_SESSION['session_timeout'] = $timeout;
        $_SESSION['cookie_timeout'] = $timeout;
        $_SESSION['id'] = session_id();
      }
    }
  }

  /**
   * Checks if the session has already been started.
   *
   * @return bool Returns true if session is active, false otherwise.
   */
  public function is_session_started(): bool
  {
    return PHP_SESSION_ACTIVE == session_status();
  }

  /**
   * Dumps session information including status, id, session folder, and related settings.
   *
   * @return array An associative array containing session-related information.
   */
  public static function dump(): array
  {
    return [
      'sessions' => [
        'active' => PHP_SESSION_NONE == session_status(),
        'id' => session_id(),
        'folder' => realpath(ini_get('session.save_path')),
        'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
        'session.cookie_lifetime' => ini_get('session.cookie_lifetime'),
        'session.gc_probability' => ini_get('session.gc_probability'),
        'session.gc_divisor' => ini_get('session.gc_divisor'),
        'session.hash_function' => ini_get('session.hash_function'),
        'session.file' => realpath(session_save_path() .'/sess_' . session_id())
      ],
      'cookies' => $_COOKIE
    ];
  }

  /**
   * Returns the current date and time in the 'Asia/Jakarta' timezone.
   *
   * @return DateTime The current date and time.
   * @throws Exception If the DateTime creation fails.
   */
  public function now(): DateTime
  {
    return new DateTime('now', new DateTimeZone('Asia/Jakarta'));
  }

  /**
   * Clears all cookies by setting their expiration date to the past.
   * This method loops through the $_COOKIE array, expires each cookie,
   * and removes it from the $_COOKIE global array.
   *
   * @return void
   */
  public static function clearCookies()
  {
    // Loop through the $_COOKIE array and delete each cookie
    foreach ($_COOKIE as $cookie_name => $cookie_value) {
      // Set cookies to expire in the past to delete them
      setcookie($cookie_name, '', time() - 3600, '/');

      // Remove the cookie from the $_COOKIE array
      unset($_COOKIE[$cookie_name]);
    }
  }

  /**
   * Clears all active session data by calling session_destroy() and
   * resetting relevant session variables.
   *
   * @return bool
   */
  public static function clearSessions()
  {
    // Check if the session is started
    if (session_status() == PHP_SESSION_ACTIVE) {
      // Destroy session data
      session_unset();
      session_destroy();
      // Clear cookies
      self::clearCookies();
      // Remove existing session file
      $session_attr = self::dump();
      $session_file = $session_attr['sessions']['session.file'];
      if ($session_file && file_exists($session_file)) {
        unlink($session_file);
      }
      return true;
    } else {
      return false;
    }
  }
}
