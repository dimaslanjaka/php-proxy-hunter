<?php

require_once __DIR__ . '/func.php';

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;

/**
 * Extracts IP:PORT pairs from a string, along with optional username and password.
 *
 * @param string|null $string The input string containing IP:PORT pairs.
 * @param \PhpProxyHunter\ProxyDB|null $db An optional ProxyDB instance for database operations.
 * @param bool $debug Flag indicating whether debug information should be displayed. Default is true.
 * @return \PhpProxyHunter\Proxy[] An array containing the extracted IP:PORT pairs along with username and password if present.
 */
function extractProxies(?string $string, ?\PhpProxyHunter\ProxyDB $db = null, bool $debug = true): array
{
  if (is_null($string) || empty(trim($string))) {
    return [];
  }

  $results = [];

  // Regular expression pattern to match IP:PORT pairs along with optional username and password
  $pattern = '/((?:(?:\d{1,3}\.){3}\d{1,3})\:\d{2,5}(?:@\w+:\w+)?|(?:(?:\w+)\:\w+@\d{1,3}(?:\.\d{1,3}){3}\:\d{2,5}))/';

  // Perform the matching
  preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);

  if (!$db) {
    $db = new \PhpProxyHunter\ProxyDB();
  }

  foreach ($matches as $match) {
    if (empty($match)) {
      continue;
    }
    $username = $password = $proxy = null;
    if (!empty($match[1]) && strpos($match[1], '@') !== false) {
      list($proxy, $login) = explode('@', $match[1]);
      $_login = $login;
      if (!isValidIPPort($proxy)) {
        $login = $proxy;
        $proxy = $_login;
      }
      list($username, $password) = explode(":", $login);
    } else {
      $proxy = $match[0];
    }

    if (!empty($proxy) && is_string($proxy) && strlen($proxy) >= 10) {
      if (isValidProxy(trim($proxy))) {
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
          $results[] = $result[0];
        } else {
          $result = new Proxy($proxy);
          if (!is_null($username) && !is_null($password)) {
            $result->username = $username;
            $result->password = $password;
            $db->updateData($proxy, ['username' => $username, 'password' => $password]);
          } else {
            $db->add($proxy);
          }
          $results[] = $result;
        }
      } else {
        if ($debug) echo "[SQLite] extractProxies delete invalid $proxy" . PHP_EOL;
        $db->remove($proxy);
      }
    }
  }

  return $results;
}

/**
 * Checks if a string is in the format of an IP address followed by a port number.
 *
 * @param string $str The string to check.
 * @return bool Returns true if the string is in the format of IP:PORT, otherwise false.
 */
function isValidIPPort(string $str): bool
{
  $str = trim($str);
  // Regular expression to match IP:PORT format
  /** @noinspection RegExpUnnecessaryNonCapturingGroup */
  $pattern = '/^(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?):(?:\d{1,5})$/';

  // Check if the string matches the pattern
  if (preg_match($pattern, $str)) {
    return true;
  } else {
    return false;
  }
}

/**
 * Validates a proxy string.
 *
 * @param string $proxy The proxy string to validate.
 * @param bool $validate_credential Whether to validate credentials if present.
 * @return bool True if the proxy is valid, false otherwise.
 */
function isValidProxy(string $proxy, bool $validate_credential = false): bool
{
  $username = $ip = $port = $password = null;
  $hasCredential = strpos($proxy, '@') !== false;

  // Extract username and password if credentials are present
  if ($hasCredential) {
    list($proxy, $credential) = explode("@", trim($proxy), 2);
    list($username, $password) = explode(":", trim($credential), 2);
  }

  // Extract IP address and port
  list($ip, $port) = explode(":", trim($proxy), 2);

  // Validate IP address
  $is_ip_valid = filter_var($ip, FILTER_VALIDATE_IP) !== false && strlen($ip) >= 7 && strpos($ip, '..') === false;

  // Validate port number
  $is_port_valid = strlen($port) >= 2 && filter_var($port, FILTER_VALIDATE_INT, array(
          "options" => array(
              "min_range" => 1,
              "max_range" => 65535
          )
      ));

  // Check if proxy is valid
  $proxyLength = strlen($proxy);
  $re = '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{2,5}/';
  $is_proxy_valid = $is_ip_valid && $is_port_valid && $proxyLength >= 10 && $proxyLength <= 21 && preg_match($re, $proxy);

  // Validate credentials if required
  if ($hasCredential && $validate_credential) {
    return $is_proxy_valid && !empty($username) && !empty($password);
  }

  return $is_proxy_valid;
}

/**
 * Check if a port is open on a given IP address.
 *
 * @param string $proxy The IP address and port to check in the format "IP:port".
 * @param int $timeout The timeout value in seconds (default is 10 seconds).
 * @return bool True if the port is open, false otherwise.
 */
function isPortOpen(string $proxy, int $timeout = 10): bool
{
  $proxy = trim($proxy);

  // disallow empty proxy
  if (empty($proxy) || strlen($proxy) < 7)
    return false;

  // Separate IP and port
  list($ip, $port) = explode(':', $proxy);

  // Create a TCP/IP socket with the specified timeout
  $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

  // Check if the socket could be opened
  if ($socket === false) {
    return false; // Port is closed
  } else {
    fclose($socket);
    return true; // Port is open
  }
}

function buildCurl($proxy, $type = 'http', string $endpoint = 'https://bing.com', array $headers = [], ?string $username = null, ?string $password = null)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint); // URL to test connectivity
  curl_setopt($ch, CURLOPT_PROXY, $proxy); // Proxy address
  if (!is_null($username) && !is_null($password)) {
    curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$username:$password"); // Set proxy authentication credentials
  }

  // Determine the CURL proxy type based on the specified $type
  $proxy_type = CURLPROXY_HTTP;
  if (strtolower($type) == 'socks5')
    $proxy_type = CURLPROXY_SOCKS5;
  if (strtolower($type) == 'socks4')
    $proxy_type = CURLPROXY_SOCKS4;
  if (strtolower($type) == 'socks4a')
    $proxy_type = CURLPROXY_SOCKS4A;
  curl_setopt($ch, CURLOPT_PROXYTYPE, $proxy_type); // Specify proxy type

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Set maximum connection time
  curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set maximum response time

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);

  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);

  $cookies = __DIR__ . '/tmp/cookies/' . sanitizeFilename($proxy) . '.txt';
  if (!file_exists(dirname($cookies)))
    mkdir(dirname($cookies), 0777, true);
  curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
  curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);

  $userAgent = randomAndroidUa();

  foreach ($headers as $header) {
    if (strpos($header, 'User-Agent:') === 0) {
      $userAgent = trim(substr($header, strlen('User-Agent:')));
      break;
    }
  }

  if (empty($userAgent))
    $userAgent = randomAndroidUa();

  curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate'); // Handle compressed response
  return $ch;
}

/**
 * Check proxy connectivity.
 *
 * This function tests the connectivity of a given proxy by making a request to a specified endpoint.
 *
 * @param string $proxy The proxy address to test.
 * @param string $type (Optional) The type of proxy to use. Supported values: 'http', 'socks4', 'socks5', 'socks4a'.
 *                     Defaults to 'http' if not specified.
 * @param string $endpoint (Optional) The URL endpoint to test connectivity. Defaults to 'https://bing.com'.
 * @param array $headers (Optional) Additional HTTP headers to include in the request. Defaults to an empty array.
 * @return array An associative array containing the result of the proxy check:
 *               - 'result': Boolean indicating if the proxy check was successful.
 *               - 'latency': The latency (in milliseconds) of the proxy connection. If the connection failed, -1 is returned.
 *               - 'error': Error message if an error occurred during the connection attempt, null otherwise.
 *               - 'status': HTTP status code of the response.
 *               - 'private': Boolean indicating if the proxy is private.
 */
function checkProxy(string $proxy, string $type = 'http', string $endpoint = 'https://bing.com', array $headers = [], ?string $username = null, ?string $password = null): array
{
  $proxy = trim($proxy);
  $ch = buildCurl($proxy, $type, $endpoint, $headers, $username, $password);
  $start = microtime(true); // Start time
  $response = curl_exec($ch);
  $end = microtime(true); // End time

  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $response_header = substr($response, 0, $header_size);
  // is private proxy?
  $isPrivate = stripos($response_header, 'X-Forwarded-For:') !== false || stripos($response_header, 'Proxy-Authorization:') !== false;

  $info = curl_getinfo($ch);
  $latency = -1;

  $result = [];

  // Check for CURL errors or empty response
  if (curl_errno($ch) || $response === false) {
    $error_msg = curl_error($ch);
    $result = [
        'result' => false,
        'latency' => $latency,
        'error' => $error_msg,
        'status' => $info['http_code'],
        'private' => $isPrivate
    ];
  }

  curl_close($ch);

  // Convert to milliseconds
  $latency = round(($end - $start) * 1000);

  if (empty($result)) {
    $result = [
        'result' => true,
        'latency' => $latency,
        'error' => null,
        'status' => $info['http_code'],
        'private' => $isPrivate
    ];
  }

  return $result;
}

function get_geo_ip(string $the_proxy, string $proxy_type = 'http', ?\PhpProxyHunter\ProxyDB $db = null)
{
  $proxy = trim($the_proxy);
  if (empty($proxy)) return;
  if (empty($db)) $db = new \PhpProxyHunter\ProxyDB();
  list($ip, $port) = explode(':', $proxy);
  /** @noinspection PhpFullyQualifiedNameUsageInspection */
  $geo_plugin = new \PhpProxyHunter\geoPlugin();
  $geoUrl = "https://ip-get-geolocation.com/api/json/$ip";
  // fetch ip info
  $content = curlGetWithProxy($geoUrl, $proxy, $proxy_type);
  if (!$content) $content = '';
  $geoIp = json_decode($content, true);
  $data = [];
  // Check if JSON decoding was successful
  if (json_last_error() === JSON_ERROR_NONE) {
    if (trim($geoIp['status']) != 'fail') {
      if (isset($geoIp['lat'])) {
        $data['latitude'] = $geoIp['lat'];
      }
      if (isset($geoIp['lon'])) {
        $data['longitude'] = $geoIp['lon'];
      }
      if (isset($geoIp['timezone'])) {
        $data['timezone'] = $geoIp['timezone'];
      }
      if (isset($geoIp['country'])) {
        $data['country'] = $geoIp['country'];
      }

      try {
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        $countries = array_values(\Annexare\Countries\countries());
        $filterCountry = array_filter($countries, function ($country) use ($geoIp, $proxy) {
          return trim(strtolower($country['name'])) == trim(strtolower($geoIp['country']));
        });
        if (!empty($filterCountry)) {
          $lang = array_values($filterCountry)[0]['languages'][0];
          if (!empty($lang)) {
            $db->updateData($proxy, ['lang' => $lang]);
          } else {
            echo "language $proxy is empty, country " . $geoIp['country'];
          }
        }
      } catch (\Throwable $th) {
        //
      }

      if (isset($geoIp['region'])) {
        $region = $geoIp['region'];
        if (!empty($geoIp['regionName'])) $region = $geoIp['regionName'];
        $data['region'] = $region;
      }
    } else {
      $cache_file = curlGetCache($geoUrl);
      if (file_exists($cache_file)) unlink($cache_file);
    }
  } else {
    $locate = $geo_plugin->locate_recursive($ip);
    if (!empty($locate->countryName)) {
      $data['country'] = $locate->countryName;
    }
    if (!empty($locate->regionName)) {
      $data['region'] = $locate->regionName;
    }
    if (!empty($locate->latitude)) {
      $data['latitude'] = $locate->latitude;
    }
    if (!empty($locate->longitude)) {
      $data['longitude'] = $locate->longitude;
    }
    if (!empty($locate->timezone)) {
      $data['timezone'] = $locate->timezone;
    }
    $lang = $locate->lang;
    $locale = country_code_to_locale($locate->countryCode);
    $ext_intl = ext_intl_get_lang_country_code($locate->countryCode);
    if (!empty($locale)) {
      $lang = $locale;
    } else if (!empty($ext_intl)) {
      $lang = $ext_intl;
    }
    if (!empty($lang)) {
      $data['lang'] = $lang;
    }
    // echo "$ip country $locate->countryName language is $lang" . PHP_EOL;
  }

  $db->updateData($proxy, $data);
}

/**
 * Retrieves the primary language based on the provided country code using the ext-intl extension.
 *
 * This function requires the PHP ext-intl extension to be enabled.
 *
 * @param string $country The country code.
 * @return string|null The primary language code or null if an error occurs or the language is not found.
 */
function ext_intl_get_lang_country_code(string $country): ?string
{
  if (empty($country)) return null;
  try {
    $subtags = \ResourceBundle::create('likelySubtags', 'ICUDATA', false);
    $country = \Locale::canonicalize('und_' . $country);
    if (($country[0] ?? null) === '_') {
      $country = 'und' . $country;
    }
    $locale = $subtags->get($country) ?: $subtags->get('und');
    return \Locale::getPrimaryLanguage($locale);
  } catch (Exception $e) {
    return null;
  }
}

/**
 * Returns a locale from a provided country code.
 *
 * @param string $country_code ISO 3166-2-alpha 2 country code
 * @param string $language_code ISO 639-1-alpha 2 language code (optional)
 * @return string|null A locale, formatted like en_US, or null if not found
 */
function country_code_to_locale(string $country_code, string $language_code = '')
{
  if (empty($country_code)) return null;

  // Locale list taken from:
  // http://stackoverflow.com/questions/3191664/
  // list-of-all-locales-and-their-short-codes
  $locales = array(
      'af-ZA',
      'am-ET',
      'ar-AE',
      'ar-BH',
      'ar-DZ',
      'ar-EG',
      'ar-IQ',
      'ar-JO',
      'ar-KW',
      'ar-LB',
      'ar-LY',
      'ar-MA',
      'arn-CL',
      'ar-OM',
      'ar-QA',
      'ar-SA',
      'ar-SY',
      'ar-TN',
      'ar-YE',
      'as-IN',
      'az-Cyrl-AZ',
      'az-Latn-AZ',
      'ba-RU',
      'be-BY',
      'bg-BG',
      'bn-BD',
      'bn-IN',
      'bo-CN',
      'br-FR',
      'bs-Cyrl-BA',
      'bs-Latn-BA',
      'ca-ES',
      'co-FR',
      'cs-CZ',
      'cy-GB',
      'da-DK',
      'de-AT',
      'de-CH',
      'de-DE',
      'de-LI',
      'de-LU',
      'dsb-DE',
      'dv-MV',
      'el-GR',
      'en-029',
      'en-AU',
      'en-BZ',
      'en-CA',
      'en-GB',
      'en-IE',
      'en-IN',
      'en-JM',
      'en-MY',
      'en-NZ',
      'en-PH',
      'en-SG',
      'en-TT',
      'en-US',
      'en-ZA',
      'en-ZW',
      'es-AR',
      'es-BO',
      'es-CL',
      'es-CO',
      'es-CR',
      'es-DO',
      'es-EC',
      'es-ES',
      'es-GT',
      'es-HN',
      'es-MX',
      'es-NI',
      'es-PA',
      'es-PE',
      'es-PR',
      'es-PY',
      'es-SV',
      'es-US',
      'es-UY',
      'es-VE',
      'et-EE',
      'eu-ES',
      'fa-IR',
      'fi-FI',
      'fil-PH',
      'fo-FO',
      'fr-BE',
      'fr-CA',
      'fr-CH',
      'fr-FR',
      'fr-LU',
      'fr-MC',
      'fy-NL',
      'ga-IE',
      'gd-GB',
      'gl-ES',
      'gsw-FR',
      'gu-IN',
      'ha-Latn-NG',
      'he-IL',
      'hi-IN',
      'hr-BA',
      'hr-HR',
      'hsb-DE',
      'hu-HU',
      'hy-AM',
      'id-ID',
      'ig-NG',
      'ii-CN',
      'is-IS',
      'it-CH',
      'it-IT',
      'iu-Cans-CA',
      'iu-Latn-CA',
      'ja-JP',
      'ka-GE',
      'kk-KZ',
      'kl-GL',
      'km-KH',
      'kn-IN',
      'kok-IN',
      'ko-KR',
      'ky-KG',
      'lb-LU',
      'lo-LA',
      'lt-LT',
      'lv-LV',
      'mi-NZ',
      'mk-MK',
      'ml-IN',
      'mn-MN',
      'mn-Mong-CN',
      'moh-CA',
      'mr-IN',
      'ms-BN',
      'ms-MY',
      'mt-MT',
      'nb-NO',
      'ne-NP',
      'nl-BE',
      'nl-NL',
      'nn-NO',
      'nso-ZA',
      'oc-FR',
      'or-IN',
      'pa-IN',
      'pl-PL',
      'prs-AF',
      'ps-AF',
      'pt-BR',
      'pt-PT',
      'qut-GT',
      'quz-BO',
      'quz-EC',
      'quz-PE',
      'rm-CH',
      'ro-RO',
      'ru-RU',
      'rw-RW',
      'sah-RU',
      'sa-IN',
      'se-FI',
      'se-NO',
      'se-SE',
      'si-LK',
      'sk-SK',
      'sl-SI',
      'sma-NO',
      'sma-SE',
      'smj-NO',
      'smj-SE',
      'smn-FI',
      'sms-FI',
      'sq-AL',
      'sr-Cyrl-BA',
      'sr-Cyrl-CS',
      'sr-Cyrl-ME',
      'sr-Cyrl-RS',
      'sr-Latn-BA',
      'sr-Latn-CS',
      'sr-Latn-ME',
      'sr-Latn-RS',
      'sv-FI',
      'sv-SE',
      'sw-KE',
      'syr-SY',
      'ta-IN',
      'te-IN',
      'tg-Cyrl-TJ',
      'th-TH',
      'tk-TM',
      'tn-ZA',
      'tr-TR',
      'tt-RU',
      'tzm-Latn-DZ',
      'ug-CN',
      'uk-UA',
      'ur-PK',
      'uz-Cyrl-UZ',
      'uz-Latn-UZ',
      'vi-VN',
      'wo-SN',
      'xh-ZA',
      'yo-NG',
      'zh-CN',
      'zh-HK',
      'zh-MO',
      'zh-SG',
      'zh-TW',
      'zu-ZA',
  );

  foreach ($locales as $locale) {
    $locale_region = locale_get_region($locale);
    $locale_language = locale_get_primary_language($locale);
    $locale_array = array(
        'language' => $locale_language,
        'region' => $locale_region
    );

    if (
        strtoupper($country_code) == $locale_region &&
        $language_code == ''
    ) {
      return locale_compose($locale_array);
    } elseif (
        strtoupper($country_code) == $locale_region &&
        strtolower($language_code) == $locale_language
    ) {
      return locale_compose($locale_array);
    }
  }

  return null;
}

/**
 * Remove lines from a file that do not contain IP:PORT format.
 *
 * ```
 * try {
 *  filterIpPortLines(__DIR__ . "/proxies.txt");
 * } catch (InvalidArgumentException $e) {
 *  echo "Lines not containing IP:PORT format remove failed. " . $e->getMessage() . PHP_EOL;
 * }
 * ```
 *
 * @param string $inputFile The path to the file.
 */
function filterIpPortLines(string $inputFile)
{
  // Check if destination file is writable
  if (!is_writable($inputFile)) {
    return "$inputFile not writable";
  }

  // Check if source file is locked
  if (is_file_locked($inputFile)) {
    return "$inputFile locked";
  }

  $str_to_remove = [];
  $content = read_file($inputFile);
  $split = array_filter(split_by_line($content));
  $results = [];
  foreach ($split as $line) {
//    if (count($str_to_remove) > 5000) break;
    if (empty(trim($line)) || strlen(trim($line)) < 10) {
      $str_to_remove[] = trim($line);
      continue;
    }
    $re = '/(?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:(?!0)\d{2,5}/';
    $containsProxy = preg_match($re, $line);
    if (!$containsProxy) {
      $str_to_remove[] = trim($line);
      echo trim($line) . " no proxy" . PHP_EOL;
      continue;
    }
    $results[] = trim($line);
  }
  // remove empty lines
//  $clean_result = preg_replace("/\n+/", "\n", implode("\n", $results));
  $clean_result = implode("\n", $results);
  file_put_contents($inputFile, $clean_result);
  return "non IP:PORT lines removed from $inputFile";
}

function clean_proxies_file(string $file)
{
  echo "remove duplicate lines $file" . PHP_EOL;

  removeDuplicateLines($file);

  echo "remove lines less than 10 size $file" . PHP_EOL;

  removeShortLines($file, 10);

  echo "remove lines not contains IP:PORT $file" . PHP_EOL;

  filterIpPortLines($file);

  echo "remove empty lines $file" . PHP_EOL;

  removeEmptyLinesFromFile($file);

  echo "fix file NUL $file" . PHP_EOL;

  fixFile($file);
}

/**
 * Parses working proxies data retrieved from the provided ProxyDB object.
 *
 * @param \PhpProxyHunter\ProxyDB $db The ProxyDB object containing the working proxies data.
 * @return array An array containing three elements:
 *               - 'txt': A string representation of working proxies, separated by newline characters and formatted as "proxy|port|type|country|last_check|useragent".
 *               - 'array': An array of associative arrays representing the working proxies data, with keys 'proxy', 'port', 'type', 'country', 'last_check', and 'useragent'.
 *               - 'counter': An array containing counts of different types of proxies in the database, including 'working', 'dead', 'untested', and 'private'.
 */
function parse_working_proxies(\PhpProxyHunter\ProxyDB $db)
{
  // Retrieve working proxies from the provided ProxyDB object
  $working = $db->getWorkingProxies();

  // Sort working proxies by the newest last_check column
  usort($working, function ($a, $b) {
    return strtotime($b['last_check']) - strtotime($a['last_check']);
  });

  // Map proxies data
  $array_mapper = array_map(function ($item) use ($db) {
    // Fill empty values with '-'
    foreach ($item as $key => $value) {
      if (empty($value)) {
        $item[$key] = '-';
      }
    }

    // Remove unneeded property
    unset($item['id']);

    // Uppercase proxy type
    $item['type'] = strtoupper($item['type']);

    // Update metadata info
    if (empty($item['useragent']) && strlen(trim($item['useragent'])) <= 5) {
      $item['useragent'] = randomWindowsUa();
      $db->updateData($item['proxy'], $item);
      // Re-fetch geolocation IP
      get_geo_ip($item['proxy']);
    }

    return $item;
  }, $working);

  // Format proxies data for working.txt file, separating each proxy by '|'
  $workingTxt = implode(PHP_EOL, array_map(function ($item) {
    return implode('|', $item);
  }, $array_mapper));

  $count = [
      'working' => $db->countWorkingProxies(),
      'dead' => $db->countDeadProxies(),
      'untested' => $db->countUntestedProxies(),
      'private' => $db->countPrivateProxies()
  ];

  return ['txt' => $workingTxt, 'array' => $array_mapper, 'counter' => $count];
}


/**
 * Extracts IP:PORT combinations from a file and processes each match using a callback function.
 *
 * @param string $filePath The path to the file containing IP:PORT combinations.
 * @param callable $callback The callback function to process each matched IP:PORT combination.
 * @throws Exception
 */
function extractIpPortFromFileCallback(string $filePath, callable $callback)
{
  if (file_exists($filePath)) {
    // Open the file for reading in binary mode
    $fp = fopen($filePath, "rb");
    if (!$fp) {
      throw new Exception('File open failed.');
    }

    // Read file line by line
    while (!feof($fp)) {
      $line = fgets($fp);

      // Match IP:PORT pattern using regular expression
      preg_match_all('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+\b/', $line, $matches);

      // Process each matched IP:PORT combination using the callback function
      foreach ($matches[0] as $match) {
        $proxy = trim($match);
        if (empty($proxy) || is_null($proxy))
          continue;
        $callback($proxy);
      }
    }

    // Close the file
    fclose($fp);
  }
}
