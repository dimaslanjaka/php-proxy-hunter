<?php
declare(strict_types=1);

require_once __DIR__ . '/../func.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__), '.env');
$dotenv->load();

$secret = getenv('CLOUD_SQLITE_SECRET');
if ($secret === false || $secret === '') {
  throw new RuntimeException('CLOUD_SQLITE_SECRET environment variable is not set.');
}

define('AUTH_TOKEN', $secret);
define('DB_FILE', __DIR__ . '/db.sqlite');

/**
 * Detects if the request is authenticated using supported methods.
 *
 * @return bool True if authenticated, false otherwise
 */
function isAuthenticated(): bool
{
  $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  $request = function_exists('parsePostData') ? parsePostData(true) : [];
  $authParam = $_GET['auth'] ?? null;
  $authPost = $request['auth'] ?? null;
  if (!is_debug()) {
    // POST and GET auth parameters are not supported in production mode
    $authParam = null;
    $authPost = null;
  }
  $token = 'Bearer ' . AUTH_TOKEN;
  return $authHeader === $token || $authParam === AUTH_TOKEN || $authPost === AUTH_TOKEN;
}
