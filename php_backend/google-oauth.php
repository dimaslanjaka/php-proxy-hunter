<?php

require_once __DIR__ . '/../func.php';
include __DIR__ . '/shared.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: *');
header('Content-Type: application/json; charset=utf-8');
header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// === Configuration ===
$host            = $_SERVER['HTTP_HOST'];
$protocol        = 'https://';
$request         = parsePostData(true);
$redirectUri     = !empty($request['redirect_uri']) ? $request['redirect_uri'] : "{$protocol}{$host}/login";
$visitorId       = $_COOKIE['visitor_id'] ?? 'CLI';
$credentialsPath = __DIR__ . "/../tmp/logins/login_{$visitorId}.json";
createParentFolders($credentialsPath);

// Validate redirect URI
if (!filter_var($redirectUri, FILTER_VALIDATE_URL)) {
  jsonResponse(['error' => 'Invalid redirect URI'], 400);
}

$client = createGoogleClient($redirectUri);

// === Handle Google Auth URL Request ===
if (!empty($request['google-auth-uri'])) {
  jsonResponse([
    'auth_uri'     => $client->createAuthUrl(),
    'redirect_uri' => $redirectUri,
  ]);
}

// === Handle OAuth Callback ===
if (!empty($request['google-oauth-callback'])) {
  $token = $client->fetchAccessTokenWithAuthCode($request['google-oauth-callback']);

  if (!empty($token['access_token'])) {
    write_file($credentialsPath, json_encode($token, JSON_PRETTY_PRINT));
    $client->setAccessToken($token);

    try {
      $google_oauth = new Google_Service_Oauth2($client);
      $info         = $google_oauth->userinfo->get();
      $email        = $info->email ?? null;

      if ($email) {
        finalizeUserSession($email, $user_db); // create or update user
        jsonResponse([
          'success' => true,
          'message' => 'Login successful',
          'email'   => $email,
        ]);
      } else {
        jsonResponse(['error' => 'Unable to get user email from Google'], 400);
      }
    } catch (\Google\Service\Exception $e) {
      jsonResponse(['error' => $e->getMessage()], 400);
    }
  }

  jsonResponse(['error' => 'Failed to fetch access token'], 400);
}

// === Initialize result ===
$result = ['error' => ['messages' => []]];

// === Load token from file ===
if (file_exists($credentialsPath)) {
  $token = json_decode(read_file($credentialsPath), true);
  if ($token) {
    $client->setAccessToken($token);
  }
}

// === Handle Authenticated User ===
if ($client->getAccessToken()) {
  refreshAccessTokenIfNeeded($client, $credentialsPath, $result);

  try {
    $google_oauth = new Google_Service_Oauth2($client);
    $info         = $google_oauth->userinfo->get();
    $email        = $info->email ?? null;

    if ($email) {
      finalizeUserSession($email, $user_db);
      $result['email'] = $email;
    }
  } catch (\Google\Service\Exception $e) {
    $result['error']['messages'][] = $e->getMessage();
  }
}

jsonResponse($result);


// === Utility Functions ===

function createGoogleClient(string $redirectUri): Google\Client
{
  $client = new Google\Client();
  $client->setClientId($_ENV['G_CLIENT_ID']);
  $client->setClientSecret($_ENV['G_CLIENT_SECRET']);
  $client->setDeveloperKey($_ENV['G_API']);
  $client->setRedirectUri($redirectUri);
  $client->addScope([
    'https://www.googleapis.com/auth/userinfo.email',
    'https://www.googleapis.com/auth/userinfo.profile',
  ]);
  $client->setAccessType('offline');
  $client->setApprovalPrompt('force');
  $client->setPrompt('select_account consent');
  $client->setApplicationName('PHP PROXY HUNTER');
  $client->setIncludeGrantedScopes(true);
  return $client;
}

function refreshAccessTokenIfNeeded(Google\Client $client, string $path, array &$result): void
{
  if ($client->isAccessTokenExpired()) {
    $refreshToken = $client->getRefreshToken();
    if ($refreshToken) {
      $client->fetchAccessTokenWithRefreshToken($refreshToken);
      write_file($path, json_encode($client->getAccessToken(), JSON_PRETTY_PRINT));
    } else {
      $result['error']['messages'][] = 'Refresh token missing or invalid';
    }
  }
}

function finalizeUserSession(string $email, \PhpProxyHunter\UserDB $user_db): void
{
  $isAdmin = $email === 'dimaslanjaka@gmail.com' || $email === ($_ENV['DJANGO_SUPERUSER_EMAIL'] ?? '');

  if (!$isAdmin && isset($_SESSION['admin'])) {
    unset($_SESSION['admin']);
  }

  $existingUser = $user_db->select($email);
  if (!$existingUser) {
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', preg_replace('/@gmail\.com$/', '', $email));
    $user_db->add([
      'email'        => $email,
      'username'     => $username,
      'password'     => bin2hex(random_bytes(8)),
      'is_staff'     => $isAdmin ? 1 : 0,
      'is_active'    => true,
      'is_superuser' => $email === 'dimaslanjaka@gmail.com',
    ]);
  }

  $_SESSION['user_id']             = $email;
  $_SESSION['authenticated']       = true;
  $_SESSION['authenticated_email'] = $email;
  if ($isAdmin) {
    $_SESSION['admin'] = true;
  }
}

function jsonResponse(array $data, int $status = 200): void
{
  http_response_code($status);
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}
