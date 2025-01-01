<?php

require_once __DIR__ . '/../func.php';

if (!empty($_POST['g-recaptcha-response'])) {
  header('Content-Type: application/json; charset=utf-8');
  $secrets = [$_ENV['G_RECAPTCHA_SECRET'], $_ENV['G_RECAPTCHA_V2_SECRET']];

  foreach ($secrets as $secret) {
    $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $secret . '&response=' . $_POST['g-recaptcha-response']);
    $responseData = json_decode($verifyResponse);

    if ($responseData->success) {
      $_SESSION['captcha'] = true;
      $_SESSION['last_captcha_check'] = date(DATE_RFC3339);
      exit(json_encode(['message' => "g-recaptcha verified successfully", "success" => true]));
    }
  }

  // If neither secret was successful
  exit(json_encode(['message' => "Error verifying g-recaptcha", "success" => false]));
}

$shortHash = $_ENV['CPID'];

// init configuration
$protocol = 'https://';
$host = !$isCli ? $_SERVER['HTTP_HOST'] : 'sh.webmanajemen.com';
$path = !$isCli ? strtok($_SERVER['REQUEST_URI'], '?') : '/data/login.php';

// Construct the full URL
$current_url = $protocol . $host . $path;
$redirectUri = $current_url;

// create Client Request to access Google API
$client = new \Google\client();
$client->setClientId($_ENV['G_CLIENT_ID']);
$client->setClientSecret($_ENV['G_CLIENT_SECRET']);
$client->setDeveloperKey($_ENV['G_API']);
$client->setRedirectUri($redirectUri);
$client->addScope("https://www.googleapis.com/auth/userinfo.email");
$client->addScope("https://www.googleapis.com/auth/userinfo.profile");
$client->setAccessType('offline');
$client->setApprovalPrompt('force');
$client->setApplicationName('PHP PROXY HUNTER');
$client->setIncludeGrantedScopes(true);

$authUri = $client->createAuthUrl();
$message = [];

if (isset($_REQUEST['login'])) {
  header('Location: ' . $authUri);
}

$credentialsPath = __DIR__ . '/../tmp/logins/login_' . (!$isCli && !empty($_COOKIE['visitor_id']) ? $_COOKIE['visitor_id'] : 'CLI') . '.json';
createParentFolders($credentialsPath);

// authenticate using saved
if (file_exists($credentialsPath)) {
  $token = json_decode(read_file($credentialsPath), true);
  if ($token) {
    $client->setAccessToken($token);
  }
}

// authenticate code from Google OAuth Flow
if (isset($_GET['code'])) {
  $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
  if (isset($token['access_token'])) {
    $client->setAccessToken($token);
    file_put_contents($credentialsPath, json_encode($token, JSON_PRETTY_PRINT));
  }
}

if ($client->getAccessToken()) {
  if ($client->isAccessTokenExpired()) {
    $message[] = 'access token expired';
    if ($client->getRefreshToken()) {
      $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
      file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
      $token_data = $client->verifyIdToken();
      $message['token_data'] = $token_data;
    }
  } else {
    // get profile info
    $google_oauth = new Google_Service_Oauth2($client);
    try {
      $google_account_info = $google_oauth->userinfo->get();
      $email = $google_account_info->email;
      $name = $google_account_info->name;
      $_SESSION['user_id'] = $email;
      // authorize captcha on logged in
      if (!empty($email)) {
        $_SESSION['captcha'] = true;
        $_SESSION['last_captcha_check'] = date(DATE_RFC3339);
      }
      if ($email == 'dimaslanjaka@gmail.com') {
        $_SESSION['admin'] = true;
      } else {
        if (isset($_SESSION['admin'])) {
          unset($_SESSION['admin']);
        }
      }
      $message['email'] = $email;
    } catch (\Google\Service\Exception $e) {
      $message[] = $e->getMessage();
    }
  }
}

if (!isset($_SESSION['captcha'])) {
  $message[] = "please resolve captcha challenge";
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
  <link rel="stylesheet"
    href="//rawcdn.githack.com/dimaslanjaka/Web-Manajemen/0f634f242ff259087c9fe176e8f28ccaebb5c015/css/all.min.css" />
</head>

<body class="mt-4 -mb-3 mr-4 ml-4 bg-white dark:bg-slate-800 dark:text-slate-400">
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <div class="inline-flex rounded-md shadow-sm mb-3" role="group">
    <button id="my_button"
      class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-700 rounded-l-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
      My Button
    </button>
    <button type="button" onclick="location.href='login.php?login=true'"
      class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-l-0 border-blue-700 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
      Login server
    </button>
    <button type="button" onclick="location.href='/proxyManager.html'"
      class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-l-0 border-blue-700 rounded-r-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
      Goto Proxy Manager
    </button>
  </div>

  <div id="recaptcha"></div>

  <div class="w-full">
    <pre class="mb-3"><code><?php var_dump($message); ?></code></pre>
  </div>

  <script>
    function send_token(token, callback) {
      if (typeof callback !== "function") callback = () => {};
      fetch(`//${location.hostname}/data/login.php`, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
          },
          body: new URLSearchParams({
              "g-recaptcha-response": token
            })
            .toString()
        })
        .then(res => res.json())
        .then(callback);
    }

    function recaptcha_execute(siteKey) {
      grecaptcha.execute(siteKey, {
        action: "submit"
      }).then(send_token);
    }

    fetch(`//${location.hostname}/info.php`).then(r => r.json()).then(res => {
      const siteKey = res["captcha-site-key"];
      const embedder = document.createElement("div");
      embedder.classList.add("g-recaptcha");
      embedder.setAttribute("data-sitekey", "X");
      embedder.setAttribute("data-callback", "send_token");
      embedder.setAttribute("data-action", "submit");
      document.getElementById("recaptcha").appendChild(embedder);
      const script = document.createElement("script");
      script.src = "https://www.google.com/recaptcha/api.js?render=" + siteKey;
      script.onload = function() {
        grecaptcha.ready(() => recaptcha_execute(siteKey));
      };
      document.body.appendChild(script);
    });
  </script>


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
      // Redirect to non-code query string
      // Check if the URL contains ?code=
      if (window.location.search.includes('code=')) {
        // Create a new URL object based on the current location
        const url = new URL(window.location.href);

        // Remove the 'code' parameter
        url.searchParams.delete('code');

        // Reload the page with the updated URL
        window.location.replace(url.toString());
      }
    };
  </script>
</body>

</html>