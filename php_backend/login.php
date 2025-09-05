<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\UserDB;

global $isCli;

if (!$isCli) {
  // Set CORS (Cross-Origin Resource Sharing) headers to allow requests from any origin
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: *');
  header('Access-Control-Allow-Methods: *');

  // Set content type to JSON with UTF-8 encoding
  header('Content-Type: application/json; charset=utf-8');

  // Ignore browser caching
  header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
}

$user_db = new UserDB(null, 'mysql', $_ENV['MYSQL_HOST'], $_ENV['MYSQL_DBNAME'], $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASS']);
$request = !$isCli ? parsePostData(is_debug()) : getopt('', ['username:', 'password:']);

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

  $response = [
    'message' => '',
    'error'   => false,
  ];

  if ($username && $password) {
    $verifyResult = verify($username, $password);
    if (isset($verifyResult['error']) && strpos($verifyResult['error'], 'unregistered') !== false && strpos($username, '@') !== false) {
      // Auto create when username is email
      $email    = $username;
      $username = explode('@', $email)[0];
      $add      = $user_db->add(['email' => $email, 'password' => $password, 'username' => $username]);
      if ($add) {
        $response['message'] = "username $username with email $email created successfully";
        $response['success'] = true;
        $verifyResult        = verify($username, $password);
        if (isset($verifyResult['success']) && $verifyResult['success'] === true) {
          $response = array_merge($response, $verifyResult);
        }
      } else {
        $response['message'] = "username $username with email $email creation failed";
        $response['error']   = true;
      }
      $response['add'] = $add;
    } else {
      // If not auto-creating, just return verify result
      if (isset($verifyResult['success']) && $verifyResult['success'] === true) {
        $response['message'] = 'Login successful';
        $response['success'] = true;
        $response            = array_merge($response, $verifyResult);
      } else {
        // Always set message as string, error as boolean
        if (isset($verifyResult['error'])) {
          $response['message'] = is_string($verifyResult['error']) ? $verifyResult['error'] : 'Login failed';
        } else {
          $response['message'] = 'Login failed';
        }
        $response['error'] = true;
      }
    }
  } else {
    $response['message'] = 'username or password empty';
    $response['error']   = true;
  }

  return $response;
}

function verify($username, $password)
{
  global $user_db;
  $response = [
    'message' => '',
    'error'   => false,
  ];
  $select = $user_db->select($username);
  if (!empty($select['password'])) {
    $verify = CustomPasswordHasher::verify($password, $select['password']);
    // Fix raw password check
    if (!$verify && $select['password'] === $password) {
      $select['password'] = CustomPasswordHasher::hash($password); // Rehash the password
      // Re-verify the password with the new hash
      $verify = CustomPasswordHasher::verify($password, $select['password']);
    }
    // If password matches, set session variables
    if ($verify) {
      // Login success
      $_SESSION['authenticated']       = true;
      $_SESSION['authenticated_email'] = strtolower($select['email']);
      if (strtolower($select['email']) == strtolower($_ENV['DJANGO_SUPERUSER_EMAIL'] ?? '')) {
        $_SESSION['admin']   = true;
        $response['success'] = true;
        $response['admin']   = true;
        $response['message'] = 'Login successful (admin)';
      } else {
        $response['success'] = true;
        $response['message'] = 'Login successful';
        if (isset($_SESSION['admin'])) {
          unset($_SESSION['admin']);
        }
      }
      $date            = new DateTime();
      $currentDateTime = $date->format('Y-m-d H:i:s.u');
      $user_db->update($select['email'], ['last_login' => $currentDateTime]);
    } else {
      $response['message'] = 'username or password mismatch';
      $response['error']   = true;
    }
  } else {
    $response['message'] = 'username or password is unregistered';
    $response['error']   = true;
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
  $dangerous_chars = ["'", '"', ';', '--', '#', '/*', '*/', '%', '_', '`'];

  // Remove dangerous characters using str_replace in a more efficient manner
  $input = str_ireplace($dangerous_chars, '', $input);

  return $input;
}
