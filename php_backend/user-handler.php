<?php

require_once __DIR__ . '/../func.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Configuration
$protocol = 'https://';
$host = $_SERVER['HTTP_HOST'];
$request = parsePostData(true);
$redirectUri = "{$protocol}{$host}/login";

$client = new Google\Client();
$client->setClientId($_ENV['G_CLIENT_ID']);
$client->setClientSecret($_ENV['G_CLIENT_SECRET']);
$client->setDeveloperKey($_ENV['G_API']);
$client->setRedirectUri($redirectUri);
$client->addScope([
  'https://www.googleapis.com/auth/userinfo.email',
  'https://www.googleapis.com/auth/userinfo.profile'
]);
$client->setAccessType('offline');
$client->setApprovalPrompt('force');
$client->setPrompt('select_account consent');
$client->setApplicationName('PHP PROXY HUNTER');
$client->setIncludeGrantedScopes(true);

$visitorId = $_COOKIE['visitor_id'] ?? 'CLI';
$credentialsPath = __DIR__ . "/../tmp/logins/login_{$visitorId}.json";
createParentFolders($credentialsPath);

// Request for auth URL
if (!empty($request['google-auth-uri'])) {
  $authUri = $client->createAuthUrl();
  jsonResponse([
    'auth_uri' => $authUri,
    'redirect_uri' => $redirectUri
  ]);
}

// Handle OAuth callback
if (!empty($request['google-oauth-callback'])) {
  $token = $client->fetchAccessTokenWithAuthCode($request['google-oauth-callback']);
  if (!empty($token['access_token'])) {
    write_file($credentialsPath, json_encode($token, JSON_PRETTY_PRINT));
    jsonResponse([
      'success' => true,
      'message' => 'Login successful',
      'token_data' => $client->verifyIdToken()
    ]);
  }
  jsonResponse(['error' => 'Failed to fetch access token'], 400);
}

// Get current user info
if (!empty($request['me'])) {
  $email = $_SESSION['user_id'] ?? null;
  if ($email) {
    $userDb = new UserDB();
    $user = $userDb->getUserByEmail($email);
    if ($user) {
      jsonResponse([
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role']
      ]);
    }
    jsonResponse(['error' => 'User not found'], 404);
  }
}

$result = ['error' => ['messages' => []]];

// Try to load existing token
if (file_exists($credentialsPath)) {
  $token = json_decode(read_file($credentialsPath), true);
  if ($token) {
    $client->setAccessToken($token);
  }
}

// Refresh or get user info
if ($client->getAccessToken()) {
  $userDb = new UserDB();

  if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
      $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
      write_file($credentialsPath, json_encode($client->getAccessToken(), JSON_PRETTY_PRINT));
    } else {
      $result['error']['messages'][] = 'Refresh token missing or invalid';
    }
  }

  try {
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    $email = $google_account_info->email ?? null;

    if ($email) {
      $_SESSION['admin'] = $email === 'dimaslanjaka@gmail.com';
      $existingUser = $userDb->getUserByEmail($email);
      if (!$existingUser) {
        $username = preg_replace('/@gmail\.com$/', '', $email);
        $userDb->createUser($username, $email, bin2hex(random_bytes(8)), $_SESSION['admin'] ? 'admin' : 'user');
      }

      $_SESSION['user_id'] = $email;
      $_SESSION['last_captcha_check'] = date(DATE_RFC3339);

      $result['email'] = $email;
    }
  } catch (\Google\Service\Exception $e) {
    $result['error']['messages'][] = $e->getMessage();
  }
}

jsonResponse($result);

// Utility
function jsonResponse(array $data, int $status = 200): void
{
  http_response_code($status);
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}
