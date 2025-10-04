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
 * @param bool $ignore_validation When true, skip calls to isValidProxy() and isValidIp() so the function
 *        will return proxies/entries even if they would normally be considered invalid. Default: false.
 * @return Proxy[] An array containing the extracted IP:PORT pairs along with username and password if present.
 */
function extractProxies(?string $string, ?ProxyDB $db = null, ?bool $write_database = false, $limit = 100, bool $ignore_validation = false)
{
  if (!$string) {
    return [];
  }
  if (empty(trim($string))) {
    return [];
  }

  $results = [];


  // Separate regex for user:pass@ip:port (allow start of line, end of line, or surrounded by whitespace)
  $pattern_userpass_ipport = '/([^@\s:]+):([^@\s:]+)@((?:\d{1,3}\.){3}\d{1,3}:\d{2,5})/';
  preg_match_all($pattern_userpass_ipport, $string, $matches_userpass_ipport, PREG_SET_ORDER);

  // Separate regex for ip:port@user:pass
  $pattern_ipport_userpass = '/((?:\d{1,3}\.){3}\d{1,3}:\d{2,5})@([^@\s:]+):([^@\s:]+)/';
  preg_match_all($pattern_ipport_userpass, $string, $matches_ipport_userpass, PREG_SET_ORDER);

  // Regex for plain ip:port
  $pattern_ipport = '/(\d{1,3}(?:\.\d{1,3}){3}:\d{2,5})/';
  preg_match_all($pattern_ipport, $string, $matches_ipport, PREG_SET_ORDER);

  // Perform the matching IP PORT (whitespaces)
  $re = '/((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s+((?!0)\d{2,5})/m';
  preg_match_all($re, $string, $matches2, PREG_SET_ORDER);
  $matched_whitespaces = !empty($matches2);

  // Perform the matching IP PORT (json) to match "ip":"x.x.x.x","port":"xxxxx"
  $pattern_json = '/"ip":"((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})".*?"port":"((?!0)\d{2,5})/m';
  preg_match_all($pattern_json, $string, $matches3, PREG_SET_ORDER);
  $matched_json = !empty($matches3);

  $matches = [];
  // Add user:pass@ip:port
  foreach ($matches_userpass_ipport as $m) {
    // $m[1]=user, $m[2]=pass, $m[3]=ip:port
    $matches[] = ['proxy' => $m[3], 'username' => $m[1], 'password' => $m[2]];
  }
  // Add ip:port@user:pass
  foreach ($matches_ipport_userpass as $m) {
    // $m[1]=ip:port, $m[2]=user, $m[3]=pass
    $matches[] = ['proxy' => $m[1], 'username' => $m[2], 'password' => $m[3]];
  }
  // Add plain ip:port (but skip if already matched above)
  $already = array_column($matches, 'proxy');
  foreach ($matches_ipport as $m) {
    if (!in_array($m[1], $already)) {
      $matches[] = ['proxy' => $m[1], 'username' => null, 'password' => null];
    }
  }
  // Add whitespace and json matches as before
  $matches = array_merge($matches, $matches2, $matches3);

  if (!$db && $write_database === true) {
    throw new \Exception('ProxyDB instance is required');
  }

  foreach ($matches as $match) {
    if (empty($match)) {
      continue;
    }
    // If this is a new-style match array (proxy, username, password)
    if (isset($match['proxy'])) {
      $proxy    = $match['proxy'];
      $username = $match['username'] ?? null;
      $password = $match['password'] ?? null;
      if ($ignore_validation || isValidProxy($proxy)) {
        $result = new Proxy($proxy);
        if (!empty($username) && !empty($password)) {
          $result->username = $username;
          $result->password = $password;
          if ($write_database === true) {
            $db->updateData($proxy, ['username' => $username, 'password' => $password, 'private' => 'true']);
          }
        }
        if (count($results) < $limit) {
          $results[] = $result;
        }
      }
      continue;
    }
    // legacy whitespace and json logic
    if ($matched_whitespaces && count($match) === 3) {
      if (!$ignore_validation && !isValidIp($match[1])) {
        continue;
      }
      $proxy  = $match[1] . ':' . $match[2];
      $result = new Proxy($proxy);
      if ($ignore_validation || isValidProxy($proxy)) {
        if (count($results) < $limit) {
          $results[] = $result;
        }
      }
      continue;
    }
    if ($matched_json && count($match) === 3) {
      $ip   = $match[1];
      $port = $match[2];
      if ($ignore_validation || isValidIp($ip)) {
        $proxy  = $ip . ':' . $port;
        $result = new Proxy($proxy);
        if ($ignore_validation || isValidProxy($proxy)) {
          if (count($results) < $limit) {
            $results[] = $result;
          }
        }
      }
      continue;
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
