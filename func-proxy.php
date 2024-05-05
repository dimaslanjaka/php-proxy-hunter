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
  $results = [];

  // Regular expression pattern to match IP:PORT pairs along with optional username and password
  /** @noinspection RegExpUnnecessaryNonCapturingGroup */
  /** @noinspection RegExpRedundantEscape */
  $pattern = '/((?:(?:\d{1,3}\.){3}\d{1,3})\:\d{2,5}(?:@\w+:\w+)?|(?:(?:\w+)\:\w+@\d{1,3}(?:\.\d{1,3}){3}\:\d{2,5}))/';

  // Perform the matching
  preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);

  // Extracted IP:PORT pairs along with optional username and password
  // $ipPorts = [];
  $db = new ProxyDB();
  foreach ($matches as $match) {
    if (empty($match)) continue;
    $username = $password = $proxy = null;
    if (!empty($match[1]) && strpos($match[1], '@') !== false) {
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

    if ($proxy != null) {
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

function get_geo_ip(string $proxy, string $proxy_type = 'http')
{
  $proxy = trim($proxy);
  $db = new ProxyDB();
  list($ip, $port) = explode(':', $proxy);
  /** @noinspection PhpFullyQualifiedNameUsageInspection */
  $geo_plugin = new \PhpProxyHunter\geoPlugin();
  $geoUrl = "https://ip-get-geolocation.com/api/json/$ip";
  // fetch ip info
  $geoIp = json_decode(curlGetWithProxy($geoUrl, $proxy, $proxy_type), true);
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
    echo "$ip country $locate->countryName language is $lang" . PHP_EOL;
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
