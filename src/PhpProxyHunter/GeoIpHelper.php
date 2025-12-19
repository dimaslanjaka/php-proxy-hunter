<?php

namespace PhpProxyHunter;

use Throwable;

/**
 * Helper class for GeoIP-related proxy operations.
 */
class GeoIpHelper {
  /**
   * Resolve geo information for a proxy and update the database.
   *
   * This was previously named `getGeoIp`.
   *
  * @param string $the_proxy Proxy string in the form "IP:PORT"
  * @param string $proxy_type Protocol type (e.g. 'http', 'socks5')
  * @param ProxyDB|null $db Optional ProxyDB instance to persist results
  * @param string|null $username Optional proxy username for authenticated proxies
  * @param string|null $password Optional proxy password for authenticated proxies
   * @return array<string,mixed> Associative array of geo data (may be empty)
   */
  public static function resolveGeoProxy($the_proxy, $proxy_type = 'http', $db = null, $username = null, $password = null) {
    $proxy = trim($the_proxy);
    if (empty($proxy)) {
      return [];
    }
    // $db is optional. If not provided, skip any database update operations.
    $parts      = explode(':', $proxy);
    $ip         = isset($parts[0]) ? $parts[0] : '';
    $port       = isset($parts[1]) ? $parts[1] : '';
    $geo_plugin = new \PhpProxyHunter\GeoPlugin();
    $geoUrl     = "https://ip-get-geolocation.com/api/json/$ip";
    $content    = curlGetWithProxy($geoUrl, $proxy, $proxy_type, 86400 * 360, getcwd() . '/.cache/', $username, $password);
    if (!$content) {
      $content = '';
    }
    $geoIp = json_decode($content, true);
    $data  = [];
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
          $countries     = array_values(\Annexare\Countries\countries());
          $filterCountry = array_filter($countries, function ($country) use ($geoIp, $proxy) {
            return trim(strtolower($country['name'])) == trim(strtolower(isset($geoIp['country']) ? $geoIp['country'] : ''));
          });
          if (!empty($filterCountry)) {
            $lang = array_values($filterCountry)[0]['languages'][0];
            if (!empty($lang) && !empty($db)) {
              $db->updateData($proxy, ['lang' => $lang]);
            }
          }
        } catch (Throwable $th) {
          echo $th->getMessage() . PHP_EOL;
        }
        if (isset($geoIp['region'])) {
          $region = $geoIp['region'];
          if (!empty($geoIp['regionName'])) {
            $region = $geoIp['regionName'];
          }
          $data['region'] = $region;
        }
      } else {
        $cache_file = curlGetCache($geoUrl);
        if (file_exists($cache_file)) {
          unlink($cache_file);
        }
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
      $lang     = $locate->lang;
      $locale   = $locate->countryCode ? self::countryCodeToLocale($locate->countryCode) : '';
      $ext_intl = $locate->countryCode ? self::extIntlGetLangCountryCode($locate->countryCode) : '';
      if (!empty($locale)) {
        $lang = $locale;
      } elseif (!empty($ext_intl)) {
        $lang = $ext_intl;
      }
      if (!empty($lang)) {
        $data['lang'] = $lang;
      }
    }
    if (!empty($db)) {
      $db->updateData($proxy, $data);
    }
    return $data;
  }

  /**
   * Retrieve simplified GeoIP information for a given IP address.
   *
   * This helper method provides a compact array of common geolocation fields
   * for the supplied IP using the GeoPlugin locator.
   *
   * Example return structure:
   * [
   *   'country'   => 'Country Name' | null,
   *   'city'      => 'City Name'    | null,
   *   'region'    => 'Region Name'  | null,
   *   'latitude'  => float|null,
   *   'longitude' => float|null,
   *   'timezone'  => 'Timezone ID'  | null,
   *   'lang'      => 'language_code'| null,
   *   'debug'     => array|null  // serialized locate object for debugging
   * ]
   *
  * @param string $ip IPv4 or IPv6 address to look up.
  * @param ProxyDB|null $db Optional ProxyDB instance to persist results
   * @return array<string,mixed> Associative array with geo information; values may be null if unavailable.
   */
  public static function getGeoIpSimple($ip, $db = null) {
    $geo_plugin        = new \PhpProxyHunter\GeoPlugin();
    $locate            = $geo_plugin->locate_recursive($ip);
    $data              = [];
    $data['country']   = $locate->countryName;
    $data['city']      = $locate->city;
    $data['region']    = $locate->regionName;
    $data['latitude']  = $locate->latitude;
    $data['longitude'] = $locate->longitude;
    $data['timezone']  = $locate->timezone;
    $data['lang']      = $locate->lang;
    $data['debug']     = $locate->jsonSerialize();
    if (!empty($db)) {
      try {
        $persist = $data;
        if (isset($persist['debug'])) {
          unset($persist['debug']);
        }
        $db->updateData($ip, $persist);
      } catch (Throwable $th) {
        // swallow DB errors to keep behavior identical when DB unavailable
      }
    }
    return $data;
  }

  /**
   * Retrieves the primary language based on the provided country code using the ext-intl extension.
   *
   * @param string $country The country code.
   * @return string|null The primary language code or null if not found.
   */
  public static function extIntlGetLangCountryCode($country) {
    if (empty($country)) {
      return null;
    }
    try {
      $subtags = \ResourceBundle::create('likelySubtags', 'ICUDATA', false);
      $country = \Locale::canonicalize('und_' . $country);
      if (isset($country[0]) && $country[0] === '_') {
        $country = 'und' . $country;
      }
      $locale = $subtags->get($country) ?: $subtags->get('und');
      return \Locale::getPrimaryLanguage($locale);
    } catch (\Exception $e) {
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
  public static function countryCodeToLocale($country_code, $language_code = '') {
    if (empty($country_code)) {
      return null;
    }
    $localesFile = __DIR__ . '/locales.json';
    if (!file_exists($localesFile)) {
      throw new \RuntimeException("Locales file not found: $localesFile");
    }
    $localesJson = file_get_contents($localesFile);
    $locales     = json_decode($localesJson, true);
    if (!is_array($locales)) {
      return null;
    }
    foreach ($locales as $locale) {
      $locale_region   = \Locale::getRegion($locale);
      $locale_language = \Locale::getPrimaryLanguage($locale);
      $locale_array    = [
        'language' => $locale_language,
        'region'   => $locale_region,
      ];
      if (
        strtoupper($country_code) == $locale_region && $language_code == ''
      ) {
        return \Locale::composeLocale($locale_array);
      } elseif (
        strtoupper($country_code) == $locale_region && strtolower($language_code) == $locale_language
      ) {
        return \Locale::composeLocale($locale_array);
      }
    }
    return null;
  }
}
