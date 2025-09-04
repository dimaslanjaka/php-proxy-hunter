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

use GeoIp2\Database\Reader;

class geoPlugin2
{
  private $city;
  private $asn;
  private $country;

  public function __construct()
  {
    $this->city = new Reader(__DIR__ . '/../GeoLite2-City.mmdb');
    $this->asn = new Reader(__DIR__ . '/../GeoLite2-ASN.mmdb');
    $this->country = new Reader(__DIR__ . '/../GeoLite2-Country.mmdb');
  }

  public function locate(string $ip)
  {
    try {
      $record = $this->city->city(trim($ip));
      $plugin = new geoPlugin();
      $plugin->fromGeoIp2CityModel($record);
      return $plugin;
    } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
      // IP not found in database, return null or handle gracefully
      return null;
    }
  }
}
