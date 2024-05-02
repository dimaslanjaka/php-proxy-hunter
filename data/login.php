<?php

$shortHash = exec('git rev-parse --short HEAD');

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
</head>

<body>
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <!-- <div id="g_id_onload" data-client_id="435643304043-alt6ls25k6c41qb76kfk34dpbc8t9c07.apps.googleusercontent.com" data-callback="handleCredentialResponse">
  </div>
  <div class="g_id_signin" data-type="standard"></div> -->
  <div id="mybtn"></div>
  <script>
    function handleCredentialResponse(response) {
      // console.log("Encoded JWT ID token: " + response.credential);
      const tokens = response.credential.split(".");
      const responsePayload = JSON.parse(atob(tokens[1]));
      console.log("ID: " + responsePayload.sub);
      console.log('Full Name: ' + responsePayload.name);
      console.log('Given Name: ' + responsePayload.given_name);
      console.log('Family Name: ' + responsePayload.family_name);
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
        document.getElementById("mybtn"), {
          theme: "outline",
          size: "large"
        } // customization attributes
      );
      google.accounts.id.prompt(); // also display the One Tap dialog
    }
  </script>
</body>

</html>