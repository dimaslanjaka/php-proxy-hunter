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

$db = new UserDB(tmp() . '/database.sqlite');
$request = !$isCli ? parsePostData(true) : getopt("", ["username:", "password:"]);

// Directly assign the username and password from the request
$username = sanitize_input($request['username'] ?? null);
$password = sanitize_input($request['password'] ?? null);

if ($username && $password) {
  $select = $db->select($username);
  if (!empty($select['password'])) {
    $verify = CustomPasswordHasher::verify($password, $select['password']);
    if ($verify) {
      // Login success
      $_SESSION['authenticated'] = true;
      die(json_encode(['success' => true]));
    } else {
      die(json_encode(['error' => 'username or password missmatch']));
    }
  } else {
    die(json_encode(['error' => 'password empty']));
  }
}

echo json_encode(['error' => 'username or password missmatch']);

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

  // List of dangerous characters to be removed
  $dangerous_chars = ["'", '"', ";", "--", "#", "/*", "*/", "%", "_", "`"];

  // Remove dangerous characters using str_replace in a more efficient manner
  $input = str_ireplace($dangerous_chars, '', $input);

  return $input;
}
