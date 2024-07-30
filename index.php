<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PHP Proxy Hunter</title>
  <meta name="yandex-verification" content="6e91ba469c56e6ac" />
  <script type="text/javascript">
    // Delay in seconds
    var delay = 5;

    // Target URL
    var url = "proxyManager.html";

    // Redirect after the delay
    setTimeout(function() {
      window.location.href = url;
    }, delay * 1000); // Convert seconds to milliseconds
  </script>
</head>

<body>
  <p>Redirecting in <span id="countdown"><?php echo $delay; ?></span> seconds...</p>
  <p><a href="proxyManager.html">Click Here To Redirect Immediately</a></p>
  <script type="text/javascript">
    // Update countdown timer
    var countdownElement = document.getElementById('countdown');
    var timeLeft = delay;
    setInterval(function() {
      if (timeLeft > 0) {
        timeLeft--;
        countdownElement.textContent = timeLeft;
      }
    }, 1000); // Update every second
  </script>
</body>

</html>