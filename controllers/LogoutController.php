<?php

if (!function_exists('tmp')) {
  require_once __DIR__ . '/../func.php';
}

use PhpProxyHunter\BaseController;

class LogoutController extends BaseController
{
  public function indexAction()
  {
    // Clear session data
    $this->clearSession();

    // Redirect to the home page or login page
    header('Location: /login');
    exit;
  }

  private function clearSession()
  {
    // Start the session if not already started
    if (session_status() == PHP_SESSION_NONE) {
      session_start();
    }

    // Unset all session variables
    $_SESSION = [];

    // If you want to destroy the session entirely, also delete the session cookie
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
      );
    }

    // Destroy the session
    session_destroy();

    // Clear all cookies
    if (isset($_SERVER['HTTP_COOKIE'])) {
      $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
      foreach ($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time() - 42000);
        setcookie($name, '', time() - 42000, '/');
      }
    }
  }
}
