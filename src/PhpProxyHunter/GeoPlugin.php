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
 */

namespace PhpProxyHunter;

/**
 * Short description of GeoPlugin.
 *
 * Longer description of the class and its responsibilities (one or two sentences).
 *
 * Example usage:
 *     $obj = new GeoPlugin();
 *
 * @package    PhpProxyHunter
 * @author     Your Name
 * @since      1.0.0
 */
class GeoPlugin implements \JsonSerializable {
  /**
   * Base URL for the GeoPlugin API.
   *
   * @var string
   */
  public $host = 'http://www.geoplugin.net/php.gp?ip={IP}&base_currency={CURRENCY}&lang={LANG}';

  /**
   * Default currency.
   *
   * @var string
   */
  public $currency = 'USD';

  /**
   * Default language.
   *
   * @var string
   */
  public $lang = 'en';

  /**
   * Client IP address.
   *
   * @var string|null
   */
  public $ip = null;

  /**
   * City name.
   *
   * @var string|null
   */
  public $city = null;

  /**
   * Region or state name.
   *
   * @var string|null
   */
  public $region = null;

  /**
   * Region or state code.
   *
   * @var string|null
   */
  public $regionCode = null;

  /**
   * Alternate name for the region or state.
   *
   * @var string|null
   */
  public $regionName = null;

  /**
   * DMA code for the area.
   *
   * @var string|null
   */
  public $dmaCode = null;

  /**
   * Country code (ISO 3166-1 alpha-2).
   *
   * @var string|null
   */
  public $countryCode = null;

  /**
   * Country name.
   *
   * @var string|null
   */
  public $countryName = null;

  /**
   * Indicates if the location is in the European Union.
   *
   * @var bool|null
   */
  public $inEU = null;

  /**
   * EU VAT rate for the location.
   *
   * @var bool
   */
  public $euVATrate = false;

  /**
   * Continent code (ISO 3166-1 alpha-2).
   *
   * @var string|null
   */
  public $continentCode = null;

  /**
   * Continent name.
   *
   * @var string|null
   */
  public $continentName = null;

  /**
   * Latitude of the location.
   *
   * @var float|null
   */
  public $latitude = null;

  /**
   * Longitude of the location.
   *
   * @var float|null
   */
  public $longitude = null;

  /**
   * Accuracy radius for the location (in meters).
   *
   * @var float|null
   */
  public $locationAccuracyRadius = null;

  /**
   * Timezone of the location.
   *
   * @var string|null
   */
  public $timezone = null;

  /**
   * Currency code (ISO 4217).
   *
   * @var string|null
   */
  public $currencyCode = null;

  /**
   * Currency symbol.
   *
   * @var string|null
   */
  public $currencySymbol = null;

  /**
   * Currency converter rate.
   *
   * @var float|null
   */
  public $currencyConverter = null;

  /**
   * Cache file path.
   *
   * @var string|null
   */
  public $cacheFile = null;

  /**
   * Constructor.
   */
  public function __construct() {
  }

  /**
   * Populate the object properties from a GeoIp2 City model.
   *
   * @param \GeoIp2\Model\City|null $record GeoIp2 City model record.
   */
  public function fromGeoIp2CityModel(\GeoIp2\Model\City $record = null) {
    if (!$record) {
      return;
    }

    $this->city        = $record->city->name;
    $this->countryName = $record->country->name;
    $this->countryCode = $record->country->isoCode;
    $this->latitude    = $record->location->latitude;
    $this->longitude   = $record->location->longitude;
    $this->timezone    = $record->location->timeZone;
    $this->regionName  = $record->mostSpecificSubdivision->name;
    $this->region      = $record->mostSpecificSubdivision->geonameId;
    $this->regionCode  = $record->mostSpecificSubdivision->isoCode;

    $langList = is_array($record->country->names) ? array_keys($record->country->names) : [];
    if (!empty($langList)) {
      $this->lang = $langList[0];
    }
  }

  #[\ReturnTypeWillChange]
  public function jsonSerialize() {
    return get_object_vars($this);
  }

  /**
   * Locate the client by IP address.
   *
   * @param string|null $ip IP address to locate. If null, uses $_SERVER['REMOTE_ADDR'].
   *
   * @return string|false JSON response from the GeoPlugin API or false on failure.
   */
  public function locate($ip = null) {
    $ip       = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null);
    $this->ip = $ip;

    $host = str_replace(
      ['{IP}', '{CURRENCY}', '{LANG}'],
      [$ip, $this->currency, $this->lang],
      $this->host
    );

    $response = $this->fetch($host);

    if ($response === false) {
      return false;
    }

    $decodedData = json_decode($response, true);
    if ($decodedData !== null && json_last_error() === JSON_ERROR_NONE) {
      if (
        ($decodedData['geoplugin_status'] ?? null) == 429 && strpos($decodedData['geoplugin_message'] ?? '', 'too many request') !== false && file_exists($this->cacheFile)
      ) {
        unlink($this->cacheFile);
      }
      return $response;
    }

    // fallback to PHP serialized format
    $data = @unserialize($response);
    if ($data !== false) {
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
    }

    return $response;
  }

  /**
   * Recursively locate the client by IP address using multiple services.
   *
   * @param string $ip IP address to locate.
   *
   * @return $this
   */
  public function locate_recursive(string $ip) {
    $geo     = $this->locate($ip);
    $decoded = json_decode($geo, true);

    if ($decoded !== null && json_last_error() === JSON_ERROR_NONE) {
      if (
        ($decoded['geoplugin_status'] ?? null) == 429 && strpos($decoded['geoplugin_message'] ?? '', 'too many request') !== false && file_exists($this->cacheFile)
      ) {
        unlink($this->cacheFile);
      }

      $geo2 = (new GeoPlugin2())->locate($ip);
      if ($geo2) {
        $this->lang        = $geo2->lang;
        $this->latitude    = $geo2->latitude;
        $this->longitude   = $geo2->longitude;
        $this->timezone    = $geo2->timezone;
        $this->city        = $geo2->city;
        $this->countryName = $geo2->countryName;
        $this->countryCode = $geo2->countryCode;
        $this->regionName  = $geo2->regionName;
        $this->region      = $geo2->region;
        $this->regionCode  = $geo2->regionCode;
      }
    }
    return $this;
  }

  /**
   * Fetch data from the GeoPlugin API.
   *
   * @param string $host URL to fetch data from.
   *
   * @return string|false Response data or false on failure.
   */
  public function fetch($host) {
    $cacheDir = getcwd() . '/.cache/';

    if (!is_dir($cacheDir)) {
      mkdir($cacheDir, 0777, true);
    }

    $this->cacheFile = $cacheDir . md5($host);

    if (file_exists($this->cacheFile)) {
      return file_get_contents($this->cacheFile);
    }

    $response   = false;
    $httpStatus = 0;

    if (function_exists('curl_init')) {
      $ch = curl_init($host);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_USERAGENT      => 'GeoPlugin PHP Class v1.1',
      ]);
      $response   = curl_exec($ch);
      $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
      $response   = @file_get_contents($host);
      $httpStatus = isset($http_response_header[0]) ? $http_response_header[0] : 0;
    } else {
      trigger_error('GeoPlugin class Error: Cannot retrieve data.', E_USER_ERROR);
      return false;
    }

    if ($httpStatus == 200 && $response !== false) {
      file_put_contents($this->cacheFile, $response);
    } elseif (file_exists($this->cacheFile)) {
      unlink($this->cacheFile);
    }

    return $response;
  }

  /**
   * Convert an amount to the local currency.
   *
   * @param float|string $amount Amount to convert.
   * @param int          $float  Number of decimal places.
   * @param bool         $symbol Whether to prepend the currency symbol.
   *
   * @return float|string Converted amount.
   */
  public function convert($amount, $float = 2, $symbol = true) {
    if (!is_numeric($this->currencyConverter) || $this->currencyConverter == 0) {
      trigger_error('GeoPlugin Notice: currencyConverter has no value.', E_USER_NOTICE);
      return $amount;
    }

    if (!is_numeric($amount)) {
      trigger_error('GeoPlugin Warning: amount is not numeric.', E_USER_WARNING);
      return $amount;
    }

    $converted = round($amount * $this->currencyConverter, $float);
    return $symbol ? $this->currencySymbol . $converted : $converted;
  }

  /**
   * Find nearby locations based on latitude and longitude.
   *
   * @param int $radius Optional. Radius in kilometers (default is 10).
   * @param int $limit  Optional. Maximum number of results to return.
   *
   * @return array<int, array<string, mixed>> List of nearby locations.
   */
  public function nearby($radius = 10, $limit = null) {
    if (!is_numeric($this->latitude) || !is_numeric($this->longitude)) {
      trigger_error('GeoPlugin Warning: Incorrect latitude / longitude.', E_USER_NOTICE);
      return [[]];
    }

    $host = "http://www.geoplugin.net/extras/nearby.gp?lat={$this->latitude}&long={$this->longitude}&radius={$radius}";

    if (is_numeric($limit)) {
      $host .= "&limit={$limit}";
    }

    return unserialize($this->fetch($host));
  }
}
