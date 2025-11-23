<?php

require_once __DIR__ . '/func.php';

use PhpProxyHunter\Session;

Session::clearSessions(true);

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Logout</title>
  <script type="text/javascript">
    // Clear local storage
    localStorage.clear();
    // Clear session storage
    sessionStorage.clear();
  </script>
</head>

<body>
  <p>You have been logged out. <a href="/">Go to home page</a></p>
</body>

</html>