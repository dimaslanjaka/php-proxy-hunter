<?php

/**
 * This PHP class is free software: you can redistribute it and/or modify
 * the code under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * However, the license header, copyright and author credits
 * must not be modified in any form and always be displayed.
 *
 * This class is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @package PhpProxyHunter
 * @version 1.2
 * @author GeoPlugin (gp_support@geoplugin.com)
 * @copyright Copyright GeoPlugin (gp_support@geoplugin.com)
 * @link http://www.geoplugin.com/webservices/php
 */

namespace PhpProxyHunter;

/**
 * A PHP class that utilizes the GeoPlugin webservice (http://www.geoplugin.com/) to geolocate IP addresses
 * and retrieve geographical information and currency details.
 */
class GeoPlugin implements \JsonSerializable
{
  /** @var string The GeoPlugin server URL */
  public $host = 'http://www.geoplugin.net/php.gp?ip={IP}&base_currency={CURRENCY}&lang={LANG}';

  /** @var string The default base currency */
  public $currency = 'USD';

  /** @var string The default language */
  public $lang = 'en';

  /** @var string|null The IP address */
  public $ip = null;

  /** @var string|null The city name */
  public $city = null;

  /** @var string|null The region */
  public $region = null;

  /** @var string|null The region code */
  public $regionCode = null;

  /** @var string|null The region name */
  public $regionName = null;

  /** @var string|null The DMA code */
  public $dmaCode = null;

  /** @var string|null The country code */
  public $countryCode = null;

  /** @var string|null The country name */
  public $countryName = null;

  /** @var bool|null Whether the IP is in the EU */
  public $inEU = null;

  /** @var mixed|null The EU VAT rate */
  public $euVATrate = false;

  /** @var string|null The continent code */
  public $continentCode = null;

  /** @var string|null The continent name */
  public $continentName = null;

  /** @var float|null The latitude */
  public $latitude = null;

  /** @var float|null The longitude */
  public $longitude = null;

  /** @var int|null The location accuracy radius */
  public $locationAccuracyRadius = null;

  /** @var string|null The timezone */
  public $timezone = null;

  /** @var string|null The currency code */
  public $currencyCode = null;

  /** @var string|null The currency symbol */
  public $currencySymbol = null;

  /** @var float|null The currency converter */
  public $currencyConverter = null;

  /** @var string|null The cache file location */
  public $cacheFile = null;

  /**
   * Initialize GeoPlugin variables.
   */
  public function __construct()
  {
    //
  }

  public function fromGeoIp2CityModel(\GeoIp2\Model\City $record = null)
  {
    if ($record != null) {
      $this->city        = $record->city->name;
      $this->countryName = $record->country->name;
      $this->countryCode = $record->country->isoCode;
      $this->latitude    = $record->location->latitude;
      $this->longitude   = $record->location->longitude;
      $this->latitude    = $record->location->latitude;
      $this->timezone    = $record->location->timeZone;
      $this->regionName  = $record->mostSpecificSubdivision->name;
      $this->region      = $record->mostSpecificSubdivision->geonameId;
      $this->regionCode  = $record->mostSpecificSubdivision->isoCode;
      $lang              = is_array($record->country->names) ? array_keys($record->country->names) : [];
      if (!empty($lang)) {
        $this->lang = $lang[0];
      }
    }
  }

  public function jsonSerialize()
  {
    return get_object_vars($this);
  }

  /**
   * Locates the geographical information and currency details for the given IP address.
   *
   * @param string|null $ip The IP address to locate. If null, uses the remote IP address.
   */
  public function locate($ip = null)
  {
    global $_SERVER;

    if (is_null($ip)) {
      $ip = $_SERVER['REMOTE_ADDR'];
    }

    $host = str_replace('{IP}', $ip, $this->host);
    $host = str_replace('{CURRENCY}', $this->currency, $host);
    $host = str_replace('{LANG}', $this->lang, $host);

    $data = [];

    // Set the GeoPlugin vars
    $this->ip = $ip;

    $response = $this->fetch($host);

    if ($response != false) {
      $decodedData = json_decode($response, true);
      if ($decodedData !== null && json_last_error() === JSON_ERROR_NONE) {
        // var_dump($decodedData);
        if (isset($decodedData['geoplugin_status']) && isset($decodedData['geoplugin_message']) && $decodedData['geoplugin_status'] == 429 && strpos($decodedData['geoplugin_message'], 'too many request') !== false) {
          // delete cache when response failed
          if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
          }
        }
      } else {
        $data = unserialize($response);
        if ($data != false) {
          $this->city                   = $data['geoplugin_city'];
          $this->region                 = $data['geoplugin_region'];
          $this->regionCode             = $data['geoplugin_regionCode'];
          $this->regionName             = $data['geoplugin_regionName'];
          $this->dmaCode                = $data['geoplugin_dmaCode'];
          $this->countryCode            = $data['geoplugin_countryCode'];
          $this->countryName            = $data['geoplugin_countryName'];
          $this->inEU                   = $data['geoplugin_inEU'];
          $this->euVATrate              = $data['geoplugin_euVATrate'];
          $this->continentCode          = $data['geoplugin_continentCode'];
          $this->continentName          = $data['geoplugin_continentName'];
          $this->latitude               = $data['geoplugin_latitude'];
          $this->longitude              = $data['geoplugin_longitude'];
          $this->locationAccuracyRadius = $data['geoplugin_locationAccuracyRadius'];
          $this->timezone               = $data['geoplugin_timezone'];
          $this->currencyCode           = $data['geoplugin_currencyCode'];
          $this->currencySymbol         = $data['geoplugin_currencySymbol'];
          $this->currencyConverter      = $data['geoplugin_currencyConverter'];
        } else {
          // echo $ip . ' geo api failed ' . PHP_EOL;
          // echo $response . PHP_EOL;
        }
      }
    }

    return $response;
  }

  /**
   * locate recursive, fallback to GeoPlugin2
   */
  public function locate_recursive(string $ip)
  {
    $geo         = $this->locate($ip);
    $decodedData = json_decode($geo, true);
    if ($decodedData !== null && json_last_error() === JSON_ERROR_NONE) {
      if (isset($decodedData['geoplugin_status']) && isset($decodedData['geoplugin_message']) && $decodedData['geoplugin_status'] == 429 && strpos($decodedData['geoplugin_message'], 'too many request') !== false) {
        // delete cache when response failed
        if (file_exists($this->cacheFile)) {
          unlink($this->cacheFile);
        }
      }
      $geo2      = new GeoPlugin2();
      $geoplugin = $geo2->locate($ip);
      if ($geoplugin != null) {
        $this->lang        = $geoplugin->lang;
        $this->latitude    = $geoplugin->latitude;
        $this->longitude   = $geoplugin->longitude;
        $this->timezone    = $geoplugin->timezone;
        $this->city        = $geoplugin->city;
        $this->countryName = $geoplugin->countryName;
        $this->countryCode = $geoplugin->countryCode;
        $this->regionName  = $geoplugin->regionName;
        $this->region      = $geoplugin->region;
        $this->regionCode  = $geoplugin->regionCode;
      }
    }
    return $this;
  }

  /**
   * Fetches data from the provided URL using cURL or fopen.
   *
   * @param string $host The URL to fetch data from.
   * @return string|false The fetched data, or false on failure.
   */
  public function fetch($host)
  {
    $cacheDir        = getcwd() . '/.cache/';
    $this->cacheFile = $cacheDir . md5($host);

    // Create cache directory if it doesn't exist
    if (!file_exists($cacheDir)) {
      mkdir($cacheDir, 0777, true);
    }

    // Return cached data if available
    if (file_exists($this->cacheFile)) {
      return file_get_contents($this->cacheFile);
    }

    // Fetch data from URL
    if (function_exists('curl_init')) {
      // Use cURL to fetch data
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $host);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_USERAGENT, 'GeoPlugin PHP Class v1.1');
      $response   = curl_exec($ch);
      $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
      // Fall back to fopen()
      $response   = file_get_contents($host);
      $httpStatus = $http_response_header[0];
    } else {
      trigger_error('GeoPlugin class Error: Cannot retrieve data. Either compile PHP with cURL support or enable allow_url_fopen in php.ini ', E_USER_ERROR);
      return false;
    }

    // Handle HTTP status and caching
    if ($httpStatus == 200 && $response !== false) {
      // Write cache
      file_put_contents($this->cacheFile, $response);
    } elseif (file_exists($this->cacheFile)) {
      // Delete cache if HTTP status is not 200 or if response is empty
      unlink($this->cacheFile);
    }

    return $response;
  }

  /**
   * Converts an amount to the geolocated currency.
   *
   * @param float $amount The amount to convert.
   * @param int $float The number of decimal places.
   * @param bool $symbol Whether to include the currency symbol.
   * @return float|string The converted amount.
   */
  public function convert($amount, $float = 2, $symbol = true)
  {
    // Easily convert amounts to geolocated currency.
    if (!is_numeric($this->currencyConverter) || $this->currencyConverter == 0) {
      trigger_error('GeoPlugin class Notice: currencyConverter has no value.', E_USER_NOTICE);
      return $amount;
    }
    if (!is_numeric($amount)) {
      trigger_error('GeoPlugin class Warning: The amount passed to GeoPlugin::convert is not numeric.', E_USER_WARNING);
      return $amount;
    }
    if ($symbol === true) {
      return $this->currencySymbol . round(($amount * $this->currencyConverter), $float);
    } else {
      return round(($amount * $this->currencyConverter), $float);
    }
  }

  /**
   * Finds nearby locations based on latitude and longitude.
   *
   * @param int $radius The search radius.
   * @param int|null $limit The maximum number of results to return.
   * @return array The nearby locations.
   */
  public function nearby($radius = 10, $limit = null)
  {
    if (!is_numeric($this->latitude) || !is_numeric($this->longitude)) {
      trigger_error('GeoPlugin class Warning: Incorrect latitude or longitude values.', E_USER_NOTICE);
      return [[]];
    }

    $host = 'http://www.geoplugin.net/extras/nearby.gp?lat=' . $this->latitude . '&long=' . $this->longitude . "&radius={$radius}";

    if (is_numeric($limit)) {
      $host .= "&limit={$limit}";
    }

    return unserialize($this->fetch($host));
  }
}
