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
  private $url_downloads = [
    'https://git.io/GeoLite2-ASN.mmdb',
    'https://git.io/GeoLite2-City.mmdb',
    'https://git.io/GeoLite2-Country.mmdb',
    'https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-City.mmdb',
    'https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-ASN.mmdb',
    'https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-Country.mmdb',
  ];

  public function __construct()
  {
    $filenames = [
      __DIR__ . '/../GeoLite2-ASN.mmdb',
      __DIR__ . '/../GeoLite2-City.mmdb',
      __DIR__ . '/../GeoLite2-Country.mmdb',
    ];
    foreach ($filenames as $filename) {
      $basename         = basename($filename);
      $findDownloadUrls = array_filter($this->url_downloads, function ($url) use ($basename) {
        return strpos($url, $basename) !== false;
      });
      $downloaded = false;
      foreach ($findDownloadUrls as $url) {
        if ($this->downloadFile($url, $filename)) {
          $downloaded = true;
          break;
        }
      }
      // Optionally, you can log or throw if all downloads fail
      if (!$downloaded) {
        // error_log("Failed to download $basename from all sources.");
      }
    }
    $this->city    = new Reader(__DIR__ . '/../GeoLite2-City.mmdb');
    $this->asn     = new Reader(__DIR__ . '/../GeoLite2-ASN.mmdb');
    $this->country = new Reader(__DIR__ . '/../GeoLite2-Country.mmdb');
  }

  private function downloadFile($url, $path)
  {
    // Helper to perform cURL with optional SSL verify
    $curl_head = function ($url, $verify = true) {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_NOBODY, true);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
      curl_exec($ch);
      $info = [
        'size'  => curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD),
        'code'  => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'error' => curl_error($ch),
      ];
      curl_close($ch);
      return $info;
    };
    $curl_get = function ($url, $verify = true) {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
      $data = curl_exec($ch);
      $info = [
        'data'  => $data,
        'code'  => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'error' => curl_error($ch),
      ];
      curl_close($ch);
      return $info;
    };

    // Get remote file size (try with SSL verify, then fallback)
    $head = $curl_head($url, true);
    if ($head['code'] === 0 && strpos($head['error'], 'SSL certificate') !== false) {
      $head = $curl_head($url, false);
    }

    $remoteFileSize = $head['size'];
    $httpCode       = $head['code'];
    $curlError      = $head['error'];

    // Get local file size
    $localFileSize = file_exists($path) ? filesize($path) : -1;

    // Only download if sizes differ or local file does not exist
    if ($httpCode == 200 && ($remoteFileSize != $localFileSize)) {
      $get = $curl_get($url, true);
      if ($get['code'] === 0 && strpos($get['error'], 'SSL certificate') !== false) {
        $get = $curl_get($url, false);
      }
      $data       = $get['data'];
      $httpCode   = $get['code'];
      $curlError2 = $get['error'];

      if ($httpCode == 200 && $data) {
        $result = @file_put_contents($path, $data);
        if ($result === false) {
          error_log("[geoPlugin2] Failed to write file: $path");
        }
        return $result !== false;
      } else {
        error_log("[geoPlugin2] Download failed for $url (HTTP $httpCode, CURL error: $curlError2)");
      }
      return false;
    } elseif ($httpCode != 200) {
      error_log("[geoPlugin2] HEAD request failed for $url (HTTP $httpCode, CURL error: $curlError)");
    }
    // No need to download, sizes match
    return true;
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
