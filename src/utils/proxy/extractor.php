<?php

if (!class_exists('ProxyDB')) {
  require_once __DIR__ . '/../../../vendor/autoload.php';
}

use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Proxy;

/**
 * Extracts all IPv4 addresses from the given string.
 *
 * This function uses a regular expression to identify and extract
 * all IP addresses in IPv4 format (e.g., 192.168.0.1) from the input string.
 *
 * @param string $string The input string potentially containing IP addresses.
 * @return string[] An array of matched IP addresses. Returns an empty array if none are found.
 */
function extractIPs($string)
{
  // Regular expression to match an IP address
  $ipPattern = '/\b(?:\d{1,3}\.){3}\d{1,3}\b/';

  // Use preg_match_all to find all IP addresses in the string
  if (preg_match_all($ipPattern, $string, $matches)) {
    return $matches[0]; // Return all matched IP addresses
  } else {
    return []; // Return empty array if no IP addresses are found
  }
}

/**
 * Extracts port numbers from a string containing IP:PORT or comma-separated ports.
 *
 * This function looks for port numbers in the format of IP:PORT (e.g., 192.168.0.1:8080),
 * and additionally supports standalone/comma-separated port lists (e.g., "80,443,8080").
 *
 * @param string $inputString The input string containing IP:PORT pairs or port numbers.
 * @return string[] An array of unique port numbers as strings.
 */
function extractPorts($inputString)
{
  $result = [];

  // Define the regular expression pattern to match IP:PORT format
  $pattern = '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:(\d{1,5})\b/';

  // Use preg_match_all to find all matches
  preg_match_all($pattern, $inputString, $matches);

  // Return the array of ports (capture group 1)
  $result = $matches[1];

  if (strpos($inputString, ',') !== false) {
    // Define the regular expression pattern to match standalone port numbers
    $pattern = '/(?<!\d)(\d{1,5})(?!\d)/';

    // Use preg_match_all to find all matches
    preg_match_all($pattern, $inputString, $matches);

    // Merge additional ports
    $result = array_merge($result, $matches[1]);
  }

  // Filter: remove empty and duplicate entries, keep strings longer than 1 character
  return array_filter(array_unique(array_filter($result)), function ($str) {
    if (empty($str)) {
      return false;
    }
    return strlen($str) > 1;
  });
}

/**
 * Extracts IP:PORT pairs from a string, along with optional username and password.
 *
 * @param string|null $string The input string containing IP:PORT pairs.
 * @param ProxyDB|null $db An optional ProxyDB instance for database operations.
 * @param bool|null $write_database An optional flag to determine if the results should be written to the database.
 * @param int $limit The maximum number of results to return.
 * @return Proxy[] An array containing the extracted IP:PORT pairs along with username and password if present.
 */
function extractProxies(?string $string, ?ProxyDB $db = null, ?bool $write_database = true, $limit = 1000)
{
  if (!$string) {
    return [];
  }
  if (empty(trim($string))) {
    return [];
  }

  $results = [];

  // Regular expression pattern to match IP:PORT pairs along with optional username and password
  $pattern = '/((?:(?:\d{1,3}\.){3}\d{1,3})\:\d{2,5}(?:@\w+:\w+)?|(?:(?:\w+)\:\w+@\d{1,3}(?:\.\d{1,3}){3}\:\d{2,5}))/';

  // Initialize $matches array
  $matches = [];

  // Perform the matching IP:PORT
  preg_match_all($pattern, $string, $matches1, PREG_SET_ORDER);

  // Perform the matching IP PORT (whitespaces)
  $re = '/((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s+((?!0)\d{2,5})/m';
  preg_match_all($re, $string, $matches2, PREG_SET_ORDER);
  $matched_whitespaces = !empty($matches2);

  // Perform the matching IP PORT (json) to match "ip":"x.x.x.x","port":"xxxxx"
  $pattern = '/"ip":"((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})".*?"port":"((?!0)\d{2,5})/m';
  preg_match_all($pattern, $string, $matches3, PREG_SET_ORDER);
  $matched_json = !empty($matches3);

  // Merge $matches1 and $matches2 into $matches
  $matches = array_merge($matches1, $matches2, $matches3);

  if (!$db) {
    $db = new ProxyDB();
  }

  foreach ($matches as $match) {
    if (empty($match)) {
      continue;
    }
    // var_dump($match, count($match));
    if ($matched_whitespaces && count($match) === 3) {
      if (!isValidIp($match[1])) {
        continue;
      }
      $proxy  = $match[1] . ':' . $match[2];
      $result = new Proxy($proxy);
      if (isValidProxy($proxy)) {
        // limit array
        if (count($results) < $limit) {
          $results[] = $result;
        }
      }
      continue;
    }
    if ($matched_json && count($match) === 3) {
      $ip   = $match[1];   // IP address
      $port = $match[2]; // Port number
      if (isValidIp($ip)) {
        $proxy  = $ip . ':' . $port;
        $result = new Proxy($proxy);
        if (isValidProxy($proxy)) {
          // limit array
          if (count($results) < $limit) {
            $results[] = $result;
          }
        }
      }
      continue;
    }
    $username = $password = $proxy = null;
    if (!empty($match[1]) && strpos($match[1], '@') !== false) {
      // list($proxy, $login) = explode('@', $match[1]);
      $exploded = explode('@', $match[1]);
      if (isValidProxy($exploded[0])) {
        $proxy = $exploded[0];
        $login = $exploded[1];
      } else {
        $proxy = $exploded[1];
        $login = $exploded[0];
      }
      list($username, $password) = explode(':', $login);
      if (isValidProxy($proxy)) {
        $result = new Proxy($proxy);
        if (!empty($username) && !empty($password) && $write_database === true) {
          $result->username = $username;
          $result->password = $password;
          $db->updateData($proxy, ['username' => $username, 'password' => $password, 'private' => 'true']);
        }
        // limit array
        if (count($results) < $limit) {
          $results[] = $result;
        }
      }
    } else {
      $proxy  = $match[0];
      $result = new Proxy($proxy);
      // limit array
      if (count($results) < $limit) {
        $results[] = $result;
      }
    }

    // if (!empty($proxy) && is_string($proxy) && strlen($proxy) >= 10) {
    //   if (isValidProxy(trim($proxy))) {
    //     $select = $db->select($proxy);
    //     if (!empty($select)) {
    //       // echo "DB EXIST" . PHP_EOL;
    //       // var_dump(!empty($username) && !empty($password));
    //       $result = array_map(function ($item) use ($username, $password) {
    //         $wrap = new Proxy($item['proxy']);
    //         foreach ($item as $key => $value) {
    //           if (property_exists($wrap, $key)) {
    //             $wrap->$key = $value;
    //           }
    //         }
    //         if (!empty($username) && !empty($password)) {
    //           $wrap->username = $username;
    //           $wrap->password = $password;
    //         }
    //         return $wrap;
    //       }, $select);
    //       $results[] = $result[0];
    //     } else {
    //       $result = new Proxy($proxy);
    //       if ($write_database) {
    //         // update database
    //         if (!empty($username) && !empty($password)) {
    //           $result->username = $username;
    //           $result->password = $password;
    //           $db->updateData($proxy, ['username' => $username, 'password' => $password, 'private' => 'true']);
    //         } else {
    //           $db->add($proxy);
    //         }
    //       }
    //       $results[] = $result;
    //     }
    //   }
    // }
  }

  return array_map(function (Proxy $item) use ($db) {
    $select = $db->select($item->proxy);
    if (!empty($select)) {
      foreach ($select[0] as $key => $value) {
        if (property_exists($item, $key)) {
          $item->$key = $value;
        }
      }
    }
    return $item;
  }, $results);
}
