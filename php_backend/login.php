<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\UserDB;

global $isCli;

if (!$isCli) {
  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: application/json; charset=utf-8');
}

$user_db = new UserDB(tmp() . '/database.sqlite');
$request = !$isCli ? parsePostData(is_debug()) : getopt("", ["username:", "password:"]);

// Directly assign the username and password from the request
$username = sanitize_input($request['username'] ?? null);
$password = sanitize_input($request['password'] ?? null);

echo json_encode(do_login($username, $password));

/**
 * Attempts to log in a user with the provided username and password.
 *
 * @param string $username The username of the user attempting to log in.
 * @param string $password The password of the user attempting to log in.
 * @return array Returns an array containing the login result.
 */
function do_login($username, $password)
{
  global $user_db;

  $response = ['error' => 'username or password empty'];

  if ($username && $password) {
    $select = $user_db->select($username);
    if (!empty($select['password'])) {
      $verify = CustomPasswordHasher::verify($password, $select['password']);
      if ($verify) {
        // Login success
        $_SESSION['authenticated'] = true;
        $_SESSION['authenticated_email'] = strtolower($select['email']);
        if (strtolower($select['email']) == strtolower($_ENV['DJANGO_SUPERUSER_EMAIL'] ?? '')) {
          $_SESSION['admin'] = true;
          $response = ['success' => true, 'admin' => true];
        } else {
          $response = ['success' => true];
          if (isset($_SESSION['admin'])) {
            unset($_SESSION['admin']);
          }
        }
        $date = new DateTime();
        $currentDateTime = $date->format('Y-m-d H:i:s.u');
        $user_db->update($select['email'], ['last_login' => $currentDateTime]);
      } else {
        $response = ['error' => 'username or password mismatch'];
      }
    } else {
      $response = ['error' => 'password empty'];
    }
  }

  return $response;
}

/**
 * Sanitize input by removing dangerous characters that could be used in SQL injection attacks.
 *
 * This function removes characters that are commonly exploited in SQL injection
 * attacks, such as quotes, semicolons, and comment markers. It ensures that the
 * input is sanitized before being used in a SQL query.
 *
 * @param string|null $input The input string to sanitize.
 * @return string|null Returns the sanitized string or null if input is empty or invalid.
 */
function sanitize_input($input)
{
  // Check if input is null or empty
  if (empty($input)) {
    return null;
  }

  // Decode input
  $input = html_entity_decode(urldecode($input));

  // List of dangerous characters to be removed
  $dangerous_chars = ["'", '"', ";", "--", "#", "/*", "*/", "%", "_", "`"];

  // Remove dangerous characters using str_replace in a more efficient manner
  $input = str_ireplace($dangerous_chars, '', $input);

  return $input;
}
