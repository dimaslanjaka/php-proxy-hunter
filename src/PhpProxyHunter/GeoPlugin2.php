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

use GeoIp2\Database\Reader;

class GeoPlugin2 {
  private $city;
  private $asn;
  private $country;

  public function __construct() {
    $cityFile    = __DIR__ . '/../GeoLite2-City.mmdb';
    $asnFile     = __DIR__ . '/../GeoLite2-ASN.mmdb';
    $countryFile = __DIR__ . '/../GeoLite2-Country.mmdb';
    try {
      $this->city    = file_exists($cityFile) ? new Reader($cityFile) : null;
      $this->asn     = file_exists($asnFile) ? new Reader($asnFile) : null;
      $this->country = file_exists($countryFile) ? new Reader($countryFile) : null;
    } catch (\Throwable $e) {
      // If the MMDB files are missing or unreadable, don't throw — leave readers null
      $this->city = $this->asn = $this->country = null;
    }
  }

  public function locate(string $ip) {
    try {
      if (empty($this->city)) {
        return null;
      }
      $record = $this->city->city(trim($ip));
      $plugin = new GeoPlugin();
      $plugin->fromGeoIp2CityModel($record);
      return $plugin;
    } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
      // IP not found in database, return null or handle gracefully
      return null;
    } catch (\Exception $e) {
      // Handle other exceptions if necessary
      return null;
    }
  }
}
