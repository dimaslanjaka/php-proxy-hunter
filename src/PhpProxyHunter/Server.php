<?php

namespace PhpProxyHunter;

class Server
{
  public static function getRequestIP()
  {
    $check = self::isCloudflare();

    if ($check) {
      return $_SERVER['HTTP_CF_CONNECTING_IP'];
    } else {
      return self::getClientIp();
    }
  }

  public static function isCloudflare(): bool
  {
    $ipCheck      = self::cloudflareCheckIp($_SERVER['REMOTE_ADDR']);
    $requestCheck = self::cloudflareRequestsCheck();

    return $ipCheck && $requestCheck;
  }

  public static function cloudflareCheckIp(?string $ip): bool
  {
    $cf_ips = [
      '199.27.128.0/21',
      '173.245.48.0/20',
      '103.21.244.0/22',
      '103.22.200.0/22',
      '103.31.4.0/22',
      '141.101.64.0/18',
      '108.162.192.0/18',
      '190.93.240.0/20',
      '188.114.96.0/20',
      '197.234.240.0/22',
      '198.41.128.0/17',
      '162.158.0.0/15',
      '104.16.0.0/12',
    ];
    $is_cf_ip = false;
    if (is_string($ip)) {
      foreach ($cf_ips as $cf_ip) {
        if (self::ipInRange($ip, $cf_ip)) {
          $is_cf_ip = true;
          break;
        }
      }
    }

    return $is_cf_ip;
  }

  /**
   * Check if a given ip is in a network.
   *
   * @see https://gist.github.com/ryanwinchester/578c5b50647df3541794
   *
   * @param string|null $ip IP to check in IPV4 format eg. 127.0.0.1
   * @param string $range IP/CIDR netmask e.g. 127.0.0.0/24, also 127.0.0.1 is accepted and /32 assumed
   *
   * @return bool true if the ip is in this range / false if not
   */
  public static function ipInRange(?string $ip, string $range): bool
  {
    if (!is_string($ip)) {
      return false;
    }
    if (!strpos($range, '/')) {
      $range .= '/32';
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      return false;
    }

    // $range is in IP/CIDR format eg 127.0.0.1/24
    list($range, $netmask) = explode('/', trim($range), 2);
    //var_dump($netmask);

    $ip_decimal       = ip2long($ip);
    $range_decimal    = ip2long($range);
    $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
    $netmask_decimal  = ~$wildcard_decimal;

    return ($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal);
  }

  // Use when handling ips

  public static function cloudflareRequestsCheck(): bool
  {
    $flag = true;

    if (!isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
      $flag = false;
    }
    if (!isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
      $flag = false;
    }
    if (!isset($_SERVER['HTTP_CF_RAY'])) {
      $flag = false;
    }
    if (!isset($_SERVER['HTTP_CF_VISITOR'])) {
      $flag = false;
    }

    return $flag;
  }

  public static function getClientIp()
  {
    $ipaddress = null;
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
      $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
      $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
      $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
      $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
      $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
      $ipaddress = $_SERVER['REMOTE_ADDR'];
    }

    if ($ipaddress === '127.0.0.1') {
      // get the actual ip address
      $ipaddress = self::getPublicIP();
    }

    return $ipaddress;
  }

  public static function getPublicIP(): ?string
  {
    static $cachedIp  = null;
    static $cacheTime = 0;

    $cacheExpiry = 3600; // 1 hour in seconds

    // Check in-memory cache first (fastest)
    if ($cachedIp !== null && (time() - $cacheTime) < $cacheExpiry) {
      return $cachedIp;
    }

    // Check file cache
    $cacheFile = __DIR__ . '/tmp/public_ip_cache.txt';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheExpiry) {
      $fileIp = trim(file_get_contents($cacheFile));
      if (filter_var($fileIp, FILTER_VALIDATE_IP)) {
        $cachedIp  = $fileIp;
        $cacheTime = time();
        return $cachedIp;
      }
    }

    // Optimized service list - fastest and most reliable first
    $ipServices = [
      ['url' => 'https://api.ipify.org', 'type' => 'text'],
      ['url' => 'https://api64.ipify.org', 'type' => 'text'],
      ['url' => 'https://ipinfo.io/ip', 'type' => 'text'],
      ['url' => 'https://ident.me', 'type' => 'text'],
      ['url' => 'https://ifconfig.me/ip', 'type' => 'text'],
      ['url' => 'https://api.myip.com', 'type' => 'json', 'field' => 'ip'],
      ['url' => 'https://httpbin.org/ip', 'type' => 'json', 'field' => 'origin'],
      ['url' => 'https://cloudflare.com/cdn-cgi/trace', 'type' => 'trace'],
    ];

    // Pre-configure cURL options to avoid repetition
    $defaultCurlOptions = [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 3, // Reduced timeout for faster failover
      CURLOPT_CONNECTTIMEOUT => 2,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
      CURLOPT_HTTPHEADER     => [
        'Accept: */*',
        'Connection: close',
      ],
    ];

    foreach ($ipServices as $service) {
      $ch = curl_init($service['url']);
      curl_setopt_array($ch, $defaultCurlOptions);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($response === false || $httpCode !== 200) {
        continue;
      }

      $ip = self::parseIpResponse($response, $service['type'], $service['field'] ?? null);
      if ($ip !== null) {
        return self::cacheIp($ip, $cacheFile, $cachedIp, $cacheTime);
      }
    }

    return null;
  }

  /**
   * Parse IP response based on service type
   */
  private static function parseIpResponse(string $response, string $type, ?string $field = null): ?string
  {
    $response = trim($response);

    switch ($type) {
      case 'text':
        if (filter_var($response, FILTER_VALIDATE_IP)) {
          return $response;
        }
        break;

      case 'json':
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data[$field])) {
          $ip = trim($data[$field]);
          if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
          }
        }
        break;

      case 'trace':
        if (preg_match('/ip=([^\n\r]+)/', $response, $matches)) {
          $ip = trim($matches[1]);
          if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
          }
        }
        break;
    }

    return null;
  }

  /**
   * Cache the IP address both in memory and file
   */
  private static function cacheIp(string $ip, string $cacheFile, ?string &$cachedIp, int &$cacheTime): string
  {
    // Cache in memory
    $cachedIp  = $ip;
    $cacheTime = time();

    // Cache in file
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
      mkdir($cacheDir, 0755, true);
    }
    file_put_contents($cacheFile, $ip);

    return $ip;
  }

  /**
   * Get Useragent.
   *
   * @return string empty when $_SERVER['HTTP_USER_AGENT'] is not set/empty
   */
  public static function useragent(): string
  {
    return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
  }

  /**
   * Get the current full URL, or null when running from CLI.
   *
   * @param bool $stripQuery Whether to remove the query string from the URL.
   * @return string|null The current URL, or null in CLI mode.
   */
  public static function getCurrentUrl(bool $stripQuery = false): ?string
  {
    if (php_sapi_name() === 'cli') {
      return null;
    }

    $url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') .
      "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    return $stripQuery ? strtok($url, '?') : $url;
  }
}
