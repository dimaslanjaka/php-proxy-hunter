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
      return self::get_client_ip();
    }
  }

  public static function isCloudflare(): bool
  {
    $ipCheck = self::_cloudflare_CheckIP($_SERVER['REMOTE_ADDR']);
    $requestCheck = self::_cloudflare_Requests_Check();

    return $ipCheck && $requestCheck;
  }

  public static function _cloudflare_CheckIP(?string $ip): bool
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
        if (self::ip_in_range($ip, $cf_ip)) {
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
  public static function ip_in_range(?string $ip, string $range): bool
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

    $ip_decimal = ip2long($ip);
    $range_decimal = ip2long($range);
    $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
    $netmask_decimal = ~$wildcard_decimal;

    return ($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal);
  }

  // Use when handling ips

  public static function _cloudflare_Requests_Check(): bool
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

  public static function get_client_ip()
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

    return $ipaddress;
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
