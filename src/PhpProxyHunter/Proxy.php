<?php

namespace PhpProxyHunter;

/**
 * Proxy table data class
 */
class Proxy
{
  /** @var int|null */
  public ?int $id = null;

  /** @var string */
  public string $proxy;

  /** @var string|null */
  public ?string $latency = null;

  /** @var string|null */
  public ?string $type = null;

  /** @var string|null */
  public ?string $region = null;

  /** @var string|null */
  public ?string $city = null;

  /** @var string|null */
  public ?string $country = null;

  /** @var string|null */
  public ?string $last_check = null;

  /** @var string|null */
  public ?string $anonymity = null;

  /** @var string|null */
  public ?string $status = null;

  /** @var string|null */
  public ?string $timezone = null;

  /** @var string|null */
  public ?string $longitude = null;

  /** @var string|null */
  public ?string $private = null;

  /** @var string|null */
  public ?string $latitude = null;

  /** @var string|null */
  public ?string $lang = null;

  /** @var string|null */
  public ?string $useragent = null;

  /** @var string|null */
  public ?string $webgl_vendor = null;

  /** @var string|null */
  public ?string $webgl_renderer = null;

  /** @var string|null */
  public ?string $browser_vendor = null;

  /** @var string|null */
  public ?string $username = null;

  /** @var string|null */
  public ?string $password = null;

  /** @var string|null */
  public ?string $https = "false";

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
    string  $proxy,
    ?string $latency = null,
    ?string $type = null,
    ?string $region = null,
    ?string $city = null,
    ?string $country = null,
    ?string $last_check = null,
    ?string $anonymity = null,
    ?string $status = null,
    ?string $timezone = null,
    ?string $longitude = null,
    ?string $private = null,
    ?string $latitude = null,
    ?string $lang = null,
    ?string $useragent = null,
    ?string $webgl_vendor = null,
    ?string $webgl_renderer = null,
    ?string $browser_vendor = null,
    ?string $username = null,
    ?string $password = null,
    ?int    $id = null,
    ?string $https = "false"
  ) {
    $this->id = $id;
    $this->proxy = $proxy;
    $this->latency = $latency;
    $this->type = $type;
    $this->region = $region;
    $this->city = $city;
    $this->country = $country;
    $this->last_check = $last_check;
    $this->anonymity = $anonymity;
    $this->status = $status;
    $this->timezone = $timezone;
    $this->longitude = $longitude;
    $this->private = $private;
    $this->latitude = $latitude;
    $this->lang = $lang;
    $this->useragent = $useragent;
    $this->webgl_vendor = $webgl_vendor;
    $this->webgl_renderer = $webgl_renderer;
    $this->browser_vendor = $browser_vendor;
    $this->username = $username;
    $this->password = $password;
    $this->https = $https;
  }
}
