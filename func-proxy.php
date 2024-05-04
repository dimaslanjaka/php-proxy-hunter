<?php

require_once __DIR__ . '/func.php';

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

/**
 * Extracts IP:PORT pairs from a string, along with optional username and password.
 *
 * @param string $string The input string containing IP:PORT pairs.
 * @return Proxy[] An array containing the extracted IP:PORT pairs along with username and password if present.
 */
function extractProxies(string $string): array
{

  // Regular expression pattern to match IP:PORT pairs along with optional username and password
  $pattern = '/((?:(?:\d{1,3}\.){3}\d{1,3})\:\d{2,5}(?:@\w+:\w+)?|(?:(?:\w+)\:\w+@\d{1,3}(?:\.\d{1,3}){3}\:\d{2,5}))/';

  // // Perform the matching
  preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);

  // Extracted IP:PORT pairs along with optional username and password
  // $ipPorts = [];
  $db = new ProxyDB();
  foreach ($matches as $match) {
    $username = $password = null;
    if (isset($match[1]) && !empty($match[1]) && strpos($match[1], '@') !== false) {
      list($proxy, $login) = explode('@', $match[1]);
      $_login = $login;
      if (!isValidIPPort($proxy)) {
        $login = $proxy;
        $proxy = $_login;
      }
      // var_dump("$proxy@$login");
      list($username, $password) = explode(":", $login);
    } else {
      $proxy = $match[0];
    }
    // var_dump("$username and $password");
    $select = $db->select($proxy);
    if (!empty($select)) {
      $result = array_map(function ($item) use ($username, $password) {
        $wrap = new Proxy($item['proxy']);
        foreach ($item as $key => $value) {
          if (property_exists($wrap, $key)) {
            $wrap->$key = $value;
          }
        }
        if (!is_null($username) && !is_null($password)) {
          $wrap->username = $username;
          $wrap->password = $password;
        }
        return $wrap;
      }, $select);
      $ipPorts[] = $result[0];
    } else {
      $result = new Proxy($ipPort);
      if (!is_null($username) && !is_null($password)) {
        $result->username = $username;
        $result->password = $password;
        $db->updateData($ipPort, ['username' => $username, 'password' => $password]);
      } else {
        $db->add($ipPort);
      }
      $ipPorts[] = $result;
    }
  }

  return $ipPorts;
}

/**
 * Checks if a string is in the format of an IP address followed by a port number.
 *
 * @param string $str The string to check.
 * @return bool Returns true if the string is in the format of IP:PORT, otherwise false.
 */
function isValidIPPort($str)
{
  $str = trim($str);
  // Regular expression to match IP:PORT format
  $pattern = '/^(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?):(?:\d{1,5})$/';

  // Check if the string matches the pattern
  if (preg_match($pattern, $str)) {
    return true;
  } else {
    return false;
  }
}
