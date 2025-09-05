<?php

require_once __DIR__ . '/func.php';

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

// Unset all session variables
$_SESSION = [];

// If you want to destroy the session entirely, also delete the session cookie
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params['path'],
    $params['domain'],
    $params['secure'],
    $params['httponly']
  );
}

// Destroy the session
session_destroy();

// Clear all cookies
if (isset($_SERVER['HTTP_COOKIE'])) {
  $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
  foreach ($cookies as $cookie) {
    $parts = explode('=', $cookie);
    $name  = trim($parts[0]);
    setcookie($name, '', time() - 42000);
    setcookie($name, '', time() - 42000, '/');
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Logout</title>
  <script type="text/javascript">
    // Clear local storage
    localStorage.clear();
    // Clear session storage
    sessionStorage.clear();
  </script>
</head>
<body>
<p>You have been logged out. <a href="proxyManager.html">Go to home page</a></p>
</body>
</html>
