<?php

declare(strict_types=1);

namespace PhpProxyHunter;

use Throwable;

/**
 * Helper class for GeoIP-related proxy operations.
 */
class GeoIpHelper
{
  /**
   * Get geo IP data for a proxy and update the database.
   *
   * @param string $the_proxy
   * @param string $proxy_type
   * @param ProxyDB|null $db
   * @return void
   */
  public static function getGeoIp(string $the_proxy, string $proxy_type = 'http', ?ProxyDB $db = null): void
  {
    $proxy = trim($the_proxy);
    if (empty($proxy)) {
      return;
    }
    if (empty($db)) {
      throw new \InvalidArgumentException('ProxyDB instance is required');
    }
    list($ip, $port) = explode(':', $proxy);
    $geo_plugin      = new \PhpProxyHunter\GeoPlugin();
    $geoUrl          = "https://ip-get-geolocation.com/api/json/$ip";
    $content         = curlGetWithProxy($geoUrl, $proxy, $proxy_type);
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
            return trim(strtolower($country['name'])) == trim(strtolower($geoIp['country']));
          });
          if (!empty($filterCountry)) {
            $lang = array_values($filterCountry)[0]['languages'][0];
            if (!empty($lang)) {
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
    $db->updateData($proxy, $data);
  }

  /**
   * Retrieves the primary language based on the provided country code using the ext-intl extension.
   *
   * @param string $country The country code.
   * @return string|null The primary language code or null if not found.
   */
  public static function extIntlGetLangCountryCode(string $country): ?string
  {
    if (empty($country)) {
      return null;
    }
    try {
      $subtags = \ResourceBundle::create('likelySubtags', 'ICUDATA', false);
      $country = \Locale::canonicalize('und_' . $country);
      if (($country[0] ?? null) === '_') {
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
  public static function countryCodeToLocale(string $country_code, string $language_code = ''): ?string
  {
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
