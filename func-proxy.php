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
function extractIpPorts(string $string): array
{
  // Regular expression pattern to match IP:PORT pairs along with optional username and password
  $pattern = '/((?:(?:\d{1,3}\.){3}\d{1,3})\:\d{2,5}(?:@(\w+):(\w+))?|(?:(?:\w+)\:\w+@\d{1,3}(?:\.\d{1,3}){3}\:\d{2,5}))/';

  // Perform the matching
  preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);

  // Extracted IP:PORT pairs along with optional username and password
  $ipPorts = [];
  $db = new ProxyDB();
  foreach ($matches as $match) {
    $ipPort = $match[1];
    $username = isset($match[2]) ? $match[2] : null;
    $password = isset($match[3]) ? $match[3] : null;
    $select = $db->select($ipPort);
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
