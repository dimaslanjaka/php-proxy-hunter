<?php

namespace PhpProxyHunter;

/**
 * Proxy table data class
 */
class Proxy
{
  /** @var int|null */
  public $id = null;

  /** @var string */
  public $proxy;

  /** @var string|null */
  public $latency = null;

  /** @var string|null */
  public $type = null;

  /** @var string|null */
  public $region = null;

  /** @var string|null */
  public $city = null;

  /** @var string|null */
  public $country = null;

  /** @var string|null */
  public $last_check = null;

  /** @var string|null */
  public $anonymity = null;

  /** @var string|null */
  public $status = null;

  /** @var string|null */
  public $timezone = null;

  /** @var string|null */
  public $longitude = null;

  /** @var string|null */
  public $private = null;

  /** @var string|null */
  public $latitude = null;

  /** @var string|null */
  public $lang = null;

  /** @var string|null */
  public $useragent = null;

  /** @var string|null */
  public $webgl_vendor = null;

  /** @var string|null */
  public $webgl_renderer = null;

  /** @var string|null */
  public $browser_vendor = null;

  /** @var string|null */
  public $username = null;

  /** @var string|null */
  public $password = null;

  /** @var string|null */
  public $https = 'false';

  /**
   * Proxy constructor.
   * @param string $proxy
   * @param string|null $latency
   * @param string|null $type
   * @param string|null $region
   * @param string|null $city
   * @param string|null $country
   * @param string|null $last_check
   * @param string|null $anonymity
   * @param string|null $status
   * @param string|null $timezone
   * @param string|null $longitude
   * @param string|null $private
   * @param string|null $latitude
   * @param string|null $lang
   * @param string|null $useragent
   * @param string|null $webgl_vendor
   * @param string|null $webgl_renderer
   * @param string|null $browser_vendor
   * @param string|null $username
   * @param string|null $password
   * @param int|null $id
   * @param string|null $https
   */
  public function __construct(
    $proxy,
    $latency = null,
    $type = null,
    $region = null,
    $city = null,
    $country = null,
    $last_check = null,
    $anonymity = null,
    $status = null,
    $timezone = null,
    $longitude = null,
    $private = null,
    $latitude = null,
    $lang = null,
    $useragent = null,
    $webgl_vendor = null,
    $webgl_renderer = null,
    $browser_vendor = null,
    $username = null,
    $password = null,
    $id = null,
    $https = 'false'
  ) {
    $this->id             = $id;
    $this->proxy          = $proxy;
    $this->latency        = $latency;
    $this->type           = $type;
    $this->region         = $region;
    $this->city           = $city;
    $this->country        = $country;
    $this->last_check     = $last_check;
    $this->anonymity      = $anonymity;
    $this->status         = $status;
    $this->timezone       = $timezone;
    $this->longitude      = $longitude;
    $this->private        = $private;
    $this->latitude       = $latitude;
    $this->lang           = $lang;
    $this->useragent      = $useragent;
    $this->webgl_vendor   = $webgl_vendor;
    $this->webgl_renderer = $webgl_renderer;
    $this->browser_vendor = $browser_vendor;
    $this->username       = $username;
    $this->password       = $password;
    $this->https          = $https;
  }

  /**
   * Returns a JSON representation of the Proxy object.
   * @return string
   */
  public function toJson($pretty = false)
  {
    if ($pretty) {
      return json_encode(get_object_vars($this), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return json_encode(get_object_vars($this), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
}
