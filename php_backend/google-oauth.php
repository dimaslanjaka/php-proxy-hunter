<?php

require_once __DIR__ . '/shared.php';


header('Content-Type: application/json; charset=utf-8');
PhpProxyHunter\Server::allowCors(true);

// === Configuration ===
$host            = $_SERVER['HTTP_HOST'] ?? 'www.webmanajemen.com';
$protocol        = 'https://';
$request         = parsePostData(true);
$redirectUri     = !empty($request['redirect_uri']) ? $request['redirect_uri'] : "{$protocol}{$host}/login";
$visitorId       = isset($_COOKIE['visitor_id']) ? $_COOKIE['visitor_id'] : 'CLI';
$credentialsPath = __DIR__ . "/../tmp/logins/login_{$visitorId}.json";
createParentFolders($credentialsPath);

// Validate redirect URI
if (!filter_var($redirectUri, FILTER_VALIDATE_URL)) {
  respond_json(['error' => 'Invalid redirect URI'], 400);
}

$client = createGoogleClient($redirectUri);

// === Handle Google Auth URL Request ===
if (!empty($request['google-auth-uri'])) {
  respond_json([
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
      $email        = isset($info->email) ? $info->email : null;

      if ($email) {
        finalizeUserSession($email, $user_db);
        // create or update user
        respond_json([
          'success' => true,
          'message' => 'Login successful',
          'email'   => $email,
        ]);
      } else {
        respond_json(['error' => 'Unable to get user email from Google'], 400);
      }
    } catch (\Google\Service\Exception $e) {
      respond_json(['error' => $e->getMessage()], 400);
    }
  }

  respond_json(['error' => 'Failed to fetch access token'], 400);
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
    $email        = isset($info->email) ? $info->email : null;

    if ($email) {
      finalizeUserSession($email, $user_db);
      $result['email'] = $email;
    }
  } catch (\Google\Service\Exception $e) {
    $result['error']['messages'][] = $e->getMessage();
  }
}

respond_json($result);


// === Utility Functions ===

/**
 * Create a configured Google Client instance.
 *
 * @param string $redirectUri
 * @return Google\Client
 */
function createGoogleClient($redirectUri) {
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

/**
 * Refresh access token if expired and update credentials file.
 *
 * @param Google\Client $client
 * @param string $path
 * @param array $result
 * @return void
 */
function refreshAccessTokenIfNeeded($client, $path, array &$result) {
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

/**
 * Finalize user session by creating or updating user in DB and setting session values.
 *
 * @param string $email
 * @param \PhpProxyHunter\UserDB $user_db
 * @return void
 */
function finalizeUserSession($email, $user_db) {
  global $log_db;
  $isEmailAdmin = in_array($email, getAdminEmails());
  $isAdmin      = $isEmailAdmin || $email === (isset($_ENV['DJANGO_SUPERUSER_EMAIL']) ? $_ENV['DJANGO_SUPERUSER_EMAIL'] : '');

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
    // Fetch the newly created user
    $existingUser = $user_db->select($email);
  }

  $_SESSION['user_id']             = $existingUser['id'] ?? null;
  $_SESSION['email']               = $email;
  $_SESSION['authenticated']       = true;
  $_SESSION['authenticated_email'] = $email;
  if ($isAdmin) {
    $_SESSION['admin'] = true;
  }

  // Log activity after successful login (any method)
  if (isset($log_db) && $log_db && isset($existingUser['id'])) {
    $log_db->log(
      $existingUser['id'],
      'LOGIN',
      null,
      'auth_user',
      null,
      ['email' => $email, 'oauth' => 'google']
    );
  }
}
