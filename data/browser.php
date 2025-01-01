<?php

require_once __DIR__ . '/../func.php';

use PhpProxyHunter\Server;

// Get current protocol (http or https)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$home = $protocol . "://" . $_SERVER['HTTP_HOST'];
// Get the current full URL
$currentUrl = $home . $_SERVER['REQUEST_URI'];
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Find out detailed information about your browser, including your IP address, geolocation, cookies, and bot detection status. Get real-time insights into your online presence.">
  <meta property="og:title" content="Your Browser Details Info - WMI">
  <meta property="og:description" content="Discover detailed insights about your browser, including IP, geolocation, cookies, and bot detection.">
  <meta property="og:image" content="https://yourwebsite.com/path/to/image.jpg">
  <meta name="twitter:title" content="Your Browser Details Info - WMI">
  <meta name="twitter:description" content="Check out your IP address, geolocation, cookies, and bot status for a detailed browser profile.">
  <meta name="twitter:image" content="https://yourwebsite.com/path/to/image.jpg">
  <meta name="robots" content="index, follow">
  <title>Your Browser Details Info - WMI</title>
  <link rel="canonical" href="<?php echo $currentUrl; ?>">
  <script src="//cdn.tailwindcss.com/3.4.3"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            clifford: '#da373d',
            ocean: '#1ca9c9',
            forest: '#228b22',
            sunset: '#ff4500',
            sky: '#87ceeb',
            sand: '#c2b280',
            berry: '#cc66cc',
            cyan: '#00ffff',
            magenta: '#ff00ff',
            polkador: '#ff6347',
            skip: '#d3d3d3', // light gray
            silver: '#c0c0c0',
            mutedGray: '#b0b0b0',
            lightGray: '#d3d3d3'
          }
        }
      }
    };
  </script>
  <link
    rel="stylesheet"
    href="//rawcdn.githack.com/dimaslanjaka/Web-Manajemen/0f634f242ff259087c9fe176e8f28ccaebb5c015/css/all.min.css" />
  <style>
    .horizontal-scroll {
      white-space: nowrap;
      /* Prevents wrapping */
      overflow-x: auto;
      /* Adds horizontal scroll if content overflows */
      width: 100%;
      /* Ensures full container width */
      display: block;
      /* Ensures block behavior */
    }
  </style>
</head>

<body class="mt-4 -mb-3 mr-4 ml-4 bg-white dark:bg-slate-800 dark:text-slate-400">
  <div class="container mx-auto px-4">
    <h1 class="text-center text-2xl font-bold mb-4">Your Browser Details</h1>
    <p class="text-center">Find out more about your current browser settings, including your IP address, geolocation, cookies, and bot detection status.</p>
    <div class="flex justify-center mb-4">
      <div class="text-center">
        <img src="//tools.ip2location.com/468x60.png" alt="IP2Location Browser Details Tool" border="0" width="468" height="60" />
      </div>
    </div>

    <div class="mb-5">
      Your IP <b><?php echo Server::get_client_ip(); ?></b> <br>
      Your unique ID <b id="uniqueId"></b>
    </div>

    <div class="mb-5">
      <pre class="overflow-x-auto">
<?php
##########################################################################
#
#	AZ Environment variables 1.04 � 2004 AZ
#	Civil Liberties Advocacy Network
#	http://clan.cyaccess.com   http://clanforum.cyaccess.com
#
#	AZenv is written in PHP & Perl. It is coded to be simple,
#	fast and have negligible load on the server.
#	AZenv is primarily aimed for programs using external scripts to
#	verify the passed Environment variables.
#	Only the absolutely necessary parameters are included.
#	AZenv is free software; you can use and redistribute it freely.
#	Please do not remove the copyright information.
#
##########################################################################

foreach ($_SERVER as $header => $value) {
  if (
    strpos($header, 'REMOTE') !== false || strpos($header, 'HTTP') !== false ||
    strpos($header, 'REQUEST') !== false
  ) {
    echo $header . ' = ' . $value . "\n";
  }
}
?>
</pre>
    </div>
  </div>

  <script>
    // Helper function to generate a random string of a given length
    function generateRandomString(length) {
      const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
      let randomString = '';
      for (let i = 0; i < length; i++) {
        randomString += characters.charAt(Math.floor(Math.random() * characters.length));
      }
      return randomString;
    }

    // Function to create a lifetime cookie with a 5-digit random value if it doesn't exist
    function createLifetimeCookie(cookieName, cookieLifetimeInDays) {
      // Check if the cookie already exists
      if (!getCookie(cookieName)) {
        // Generate a 5-character random value (letters and digits)
        const randomValue = generateRandomString(5);

        // Set the expiration date
        const date = new Date();
        date.setTime(date.getTime() + (cookieLifetimeInDays * 24 * 60 * 60 * 1000)); // cookieLifetimeInDays in days

        // Set the cookie with the random value and expiration
        document.cookie = `${cookieName}=${randomValue}; expires=${date.toUTCString()}; path=/`;
        console.log(`Cookie created: ${cookieName}=${randomValue}`);
      } else {
        console.log(`Cookie ${cookieName} already exists.`);
      }
    }

    // Getter function to retrieve a cookie value by its name
    function getCookie(cookieName) {
      const name = cookieName + "=";
      const decodedCookies = decodeURIComponent(document.cookie);
      const cookieArray = decodedCookies.split('; ');
      for (let i = 0; i < cookieArray.length; i++) {
        if (cookieArray[i].startsWith(name)) {
          return cookieArray[i].substring(name.length);
        }
      }
      return null; // Return null if cookie is not found
    }

    // Usage example
    createLifetimeCookie('myRandomCookie', 7); // Create cookie if it doesn't exist
    document.getElementById('uniqueId').innerHTML = getCookie('myRandomCookie'); // Retrieve and log the cookie value
  </script>
  <script async="" src="https://www.googletagmanager.com/gtag/js?id=G-BG75CLNJZ1"></script>
  <script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
      dataLayer.push(arguments);
    }
    gtag('js', new Date());

    gtag('config', 'G-BG75CLNJZ1');
  </script>
</body>

</html>