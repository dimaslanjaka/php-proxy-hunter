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
 * @author geoPlugin (gp_support@geoplugin.com)
 * @copyright Copyright geoPlugin (gp_support@geoplugin.com)
 * @link http://www.geoplugin.com/webservices/php
 */

namespace PhpProxyHunter;

/**
 * A PHP class that utilizes the geoPlugin webservice (http://www.geoplugin.com/) to geolocate IP addresses
 * and retrieve geographical information and currency details.
 */
class geoPlugin implements \JsonSerializable
{
  /** @var string The geoPlugin server URL */
  var $host = 'http://www.geoplugin.net/php.gp?ip={IP}&base_currency={CURRENCY}&lang={LANG}';

  /** @var string The default base currency */
  var $currency = 'USD';

  /** @var string The default language */
  var $lang = 'en';

  /** @var string|null The IP address */
  var $ip = null;

  /** @var string|null The city name */
  var $city = null;

  /** @var string|null The region */
  var $region = null;

  /** @var string|null The region code */
  var $regionCode = null;

  /** @var string|null The region name */
  var $regionName = null;

  /** @var string|null The DMA code */
  var $dmaCode = null;

  /** @var string|null The country code */
  var $countryCode = null;

  /** @var string|null The country name */
  var $countryName = null;

  /** @var bool|null Whether the IP is in the EU */
  var $inEU = null;

  /** @var mixed|null The EU VAT rate */
  var $euVATrate = false;

  /** @var string|null The continent code */
  var $continentCode = null;

  /** @var string|null The continent name */
  var $continentName = null;

  /** @var float|null The latitude */
  var $latitude = null;

  /** @var float|null The longitude */
  var $longitude = null;

  /** @var int|null The location accuracy radius */
  var $locationAccuracyRadius = null;

  /** @var string|null The timezone */
  var $timezone = null;

  /** @var string|null The currency code */
  var $currencyCode = null;

  /** @var string|null The currency symbol */
  var $currencySymbol = null;

  /** @var float|null The currency converter */
  var $currencyConverter = null;

  /**
   * Initialize geoPlugin variables.
   */
  function __construct()
  {
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
  function locate($ip = null)
  {
    global $_SERVER;

    if (is_null($ip)) {
      $ip = $_SERVER['REMOTE_ADDR'];
    }

    $host = str_replace('{IP}', $ip, $this->host);
    $host = str_replace('{CURRENCY}', $this->currency, $host);
    $host = str_replace('{LANG}', $this->lang, $host);

    $data = array();

    $response = $this->fetch($host);

    $data = unserialize($response);

    // Set the geoPlugin vars
    $this->ip = $ip;
    $this->city = $data['geoplugin_city'];
    $this->region = $data['geoplugin_region'];
    $this->regionCode = $data['geoplugin_regionCode'];
    $this->regionName = $data['geoplugin_regionName'];
    $this->dmaCode = $data['geoplugin_dmaCode'];
    $this->countryCode = $data['geoplugin_countryCode'];
    $this->countryName = $data['geoplugin_countryName'];
    $this->inEU = $data['geoplugin_inEU'];
    $this->euVATrate = $data['geoplugin_euVATrate'];
    $this->continentCode = $data['geoplugin_continentCode'];
    $this->continentName = $data['geoplugin_continentName'];
    $this->latitude = $data['geoplugin_latitude'];
    $this->longitude = $data['geoplugin_longitude'];
    $this->locationAccuracyRadius = $data['geoplugin_locationAccuracyRadius'];
    $this->timezone = $data['geoplugin_timezone'];
    $this->currencyCode = $data['geoplugin_currencyCode'];
    $this->currencySymbol = $data['geoplugin_currencySymbol'];
    $this->currencyConverter = $data['geoplugin_currencyConverter'];

    return $response;
  }

  /**
   * Fetches data from the provided URL using cURL or fopen.
   *
   * @param string $host The URL to fetch data from.
   * @return string The fetched data.
   */
  function fetch($host)
  {
    $cacheFile = getcwd() . '/.cache/' . md5($host);
    if (!file_exists(dirname($cacheFile))) mkdir(dirname($cacheFile));
    // return the cache
    if (file_exists($cacheFile)) return file_get_contents($cacheFile);

    if (function_exists('curl_init')) {
      // Use cURL to fetch data
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $host);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_USERAGENT, 'geoPlugin PHP Class v1.1');
      $response = curl_exec($ch);
      curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
      // Fall back to fopen()
      $response = file_get_contents($host, 'r');
    } else {
      trigger_error('geoPlugin class Error: Cannot retrieve data. Either compile PHP with cURL support or enable allow_url_fopen in php.ini ', E_USER_ERROR);
      return;
    }

    $unserialize = unserialize($response);
    if ($unserialize['geoplugin_status'] == 200) {
      // write cache
      file_put_contents($cacheFile, $response);
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
  function convert($amount, $float = 2, $symbol = true)
  {
    // Easily convert amounts to geolocated currency.
    if (!is_numeric($this->currencyConverter) || $this->currencyConverter == 0) {
      trigger_error('geoPlugin class Notice: currencyConverter has no value.', E_USER_NOTICE);
      return $amount;
    }
    if (!is_numeric($amount)) {
      trigger_error('geoPlugin class Warning: The amount passed to geoPlugin::convert is not numeric.', E_USER_WARNING);
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
  function nearby($radius = 10, $limit = null)
  {
    if (!is_numeric($this->latitude) || !is_numeric($this->longitude)) {
      trigger_error('geoPlugin class Warning: Incorrect latitude or longitude values.', E_USER_NOTICE);
      return array(array());
    }

    $host = "http://www.geoplugin.net/extras/nearby.gp?lat=" . $this->latitude . "&long=" . $this->longitude . "&radius={$radius}";

    if (is_numeric($limit)) {
      $host .= "&limit={$limit}";
    }

    return unserialize($this->fetch($host));
  }
}
