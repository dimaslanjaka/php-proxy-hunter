<?php

require_once __DIR__ . '/../func.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
  // Start the session
  session_start();
}

$shortHash = $_ENV['CPID'];

// init configuration
$clientID = $_ENV['G_CLIENT_ID'];
$clientSecret = $_ENV['G_CLIENT_SECRET'];
// Get the protocol
//$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$protocol = 'https://';
// Get the host
$host = $_SERVER['HTTP_HOST'];

// Get the path
$path = strtok($_SERVER['REQUEST_URI'], '?');

// Construct the full URL
$current_url = $protocol . $host . $path;
$redirectUri = $current_url;

// create Client Request to access Google API
$client = new Google_Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

$message = $authUri = $client->createAuthUrl();

// authenticate code from Google OAuth Flow
if (isset($_GET['code'])) {
  $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
  if (isset($token['access_token'])) {
    $client->setAccessToken($token['access_token']);

    // get profile info
    $google_oauth = new Google_Service_Oauth2($client);
    try {
      $google_account_info = $google_oauth->userinfo->get();
      $email = $google_account_info->email;
      $name = $google_account_info->name;
      if ($email == 'dimaslanjaka@gmail.com') {
        $_SESSION['admin'] = true;
      } else {
        if (isset($_SESSION['admin'])) unset($_SESSION['admin']);
      }
//      header('Content-Type:text/plain; charset=UTF-8');
//      exit(var_dump($_SESSION));
    } catch (\Google\Service\Exception $e) {
      $message = $e->getMessage();
    }
  }
} else if (isset($_REQUEST['login'])) {
  header('Location: ' . $authUri);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <meta name="description" content="Proxy Hunter By L3n4r0x" />
  <link rel="canonical" href="https://www.webmanajemen.com" />
  <script src="//cdn.tailwindcss.com/3.4.3"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            clifford: "#da373d"
          }
        }
      }
    };
  </script>
  <style>
    pre {
      white-space: pre-wrap;
      /* css-3 */
      white-space: -moz-pre-wrap;
      /* Mozilla, since 1999 */
      white-space: -pre-wrap;
      /* Opera 4-6 */
      white-space: -o-pre-wrap;
      /* Opera 7 */
      word-wrap: break-word;
      /* Internet Explorer 5.5+ */
    }
  </style>
  <link rel="stylesheet" href="//rawcdn.githack.com/dimaslanjaka/Web-Manajemen/0f634f242ff259087c9fe176e8f28ccaebb5c015/css/all.min.css" />
</head>

<body class="mt-4 -mb-3 mr-4 ml-4 bg-white dark:bg-slate-800 dark:text-slate-400">
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <div class="inline-flex rounded-md shadow-sm mb-3" role="group">
    <button id="my_button" class="px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-s-lg hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:text-white dark:hover:bg-gray-700 dark:focus:ring-blue-500 dark:focus:text-white">
    </button>
    <button type="button" class="px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-e-lg hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:text-white dark:hover:bg-gray-700 dark:focus:ring-blue-500 dark:focus:text-white" onclick="location.href='login.php?login=true'">
      Login server
    </button>
  </div>

<!--  <div class="w-full">-->
<!--    <pre class="mb-3"><code>--><?php //echo $message; ?><!--</code></pre>-->
<!--    <pre class="mb-3"><code>--><?php //echo json_encode($_ENV); ?><!--</code></pre>-->
<!--  </div>-->

  <script>
    function handleCredentialResponse(response) {
      // console.log("Encoded JWT ID token: " + response.credential);
      const tokens = response.credential.split(".");
      const responsePayload = JSON.parse(atob(tokens[1]));
      console.log("ID: " + responsePayload.sub);
      console.log("Full Name: " + responsePayload.name);
      console.log("Given Name: " + responsePayload.given_name);
      console.log("Family Name: " + responsePayload.family_name);
      console.log("Image URL: " + responsePayload.picture);
      console.log("Email: " + responsePayload.email);
      // Get current time
      var now = new Date();

      // Set expiration time to 1 hour from now
      var expirationTime = new Date(now.getTime() + 1 * 3600 * 1000); // 1 hour = 3600 seconds * 1000 milliseconds

      // Construct the cookie string
      var cookieString = "<?php echo $shortHash; ?>=" + encodeURIComponent(tokens[1]) + "; expires=" + expirationTime.toUTCString() + "; path=/";

      // Set the cookie
      document.cookie = cookieString;
    }

    window.onload = function() {
      google.accounts.id.initialize({
        client_id: "435643304043-alt6ls25k6c41qb76kfk34dpbc8t9c07.apps.googleusercontent.com",
        callback: handleCredentialResponse
      });
      google.accounts.id.renderButton(
        document.getElementById("my_button"), {
          theme: "outline",
          size: "large"
        } // customization attributes
      );
      // google.accounts.id.prompt(); // also display the One Tap dialog
    };
  </script>
</body>

</html>