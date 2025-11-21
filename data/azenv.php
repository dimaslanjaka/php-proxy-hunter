<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
  <title>AZ Environment variables 1.04</title>
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
</head>

<body>
  <pre>
<?php
##########################################################################
#
#   AZ Environment variables 1.04 � 2004 AZ
#   Civil Liberties Advocacy Network
#   http://clan.cyaccess.com   http://clanforum.cyaccess.com
#
#   AZenv is written in PHP & Perl. It is coded to be simple,
#   fast and have negligible load on the server.
#   AZenv is primarily aimed for programs using external scripts to
#   verify the passed Environment variables.
#   Only the absolutely necessary parameters are included.
#   AZenv is free software; you can use and redistribute it freely.
#   Please do not remove the copyright information.
#
##########################################################################

foreach ($_SERVER as $header => $value) {
  if (
    strpos($header, 'REMOTE') !== false || strpos($header, 'HTTP') !== false || strpos($header, 'REQUEST') !== false
  ) {
    echo $header . ' = ' . $value . "\n";
  }
}
?>
</pre>
</body>

</html>