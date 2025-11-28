<?php

namespace PhpProxyHunter;

/**
 * Proxy table data class
 */
class Proxy {
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
   * Creates a Proxy object from either a proxy string or an associative array.
   *
   * If $proxy is a string, it becomes the proxy address directly.
   * If $proxy is an array, all matching keys will be mapped to the object's properties.
   *
   * @param string|array $proxy          Proxy string (e.g. "1.2.3.4:8080") or array with proxy data.
   * @param string|null  $latency        Proxy latency in ms.
   * @param string|null  $type           Proxy type (HTTP, SOCKS4, SOCKS5, etc.).
   * @param string|null  $region         Region of the proxy.
   * @param string|null  $city           City of the proxy.
   * @param string|null  $country        Country of the proxy.
   * @param string|null  $last_check     Timestamp of the last check.
   * @param string|null  $anonymity      Level of anonymity.
   * @param string|null  $status         Status (e.g. "online", "offline").
   * @param string|null  $timezone       Proxy timezone.
   * @param string|null  $longitude      Longitude coordinate.
   * @param string|null  $private        Private/public flag.
   * @param string|null  $latitude       Latitude coordinate.
   * @param string|null  $lang           Language code.
   * @param string|null  $useragent      User-Agent string.
   * @param string|null  $webgl_vendor   WebGL vendor fingerprint.
   * @param string|null  $webgl_renderer WebGL renderer fingerprint.
   * @param string|null  $browser_vendor Browser vendor fingerprint.
   * @param string|null  $username       Username (if proxy has authentication).
   * @param string|null  $password       Password (if proxy has authentication).
   * @param int|null     $id             Internal database ID.
   * @param string|null  $https          Whether proxy supports HTTPS ("true"/"false").
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

    if (is_string($proxy)) {
      $this->proxy = $proxy;
    } elseif (is_array($proxy) && isset($proxy['proxy'])) {
      foreach ($proxy as $key => $value) {
        if (property_exists($this, $key) && $key !== 'proxy') {
          $this->$key = $value;
        }
      }
    } else {
      throw new \InvalidArgumentException('Proxy must be a string or an array with a "proxy" key.');
    }
  }

  /**
   * Returns a JSON representation of the Proxy object.
   *
   * @param bool $pretty   If true, returns pretty-printed JSON.
   * @param bool $notEmpty If true, only includes properties that are not null or empty.
   * @return string        JSON encoded string representing the Proxy object.
   */
  public function toJson($pretty = false, $notEmpty = false) {
    $data = get_object_vars($this);
    if ($notEmpty) {
      $data = array_filter($data, function ($value) {
        return !is_null($value) && $value !== '';
      });
    }
    if ($pretty) {
      return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  /**
   * Magic string conversion.
   * Allows (string)$proxyObj and "class proxy: $proxyObj" to produce a useful string.
   * We return a compact JSON of non-empty properties for readability.
   *
   * @return string
   */
  public function __toString() {
    // Return in format: proxy@user:pass
    // Omit parts that are empty: if no username, return just proxy; if no password, return proxy@user
    $vars  = get_object_vars($this);
    $proxy = isset($vars['proxy']) ? (string)$vars['proxy'] : '';
    $user  = isset($vars['username']) && $vars['username'] !== null && $vars['username'] !== '' ? (string)$vars['username'] : null;
    $pass  = isset($vars['password']) && $vars['password'] !== null && $vars['password'] !== '' ? (string)$vars['password'] : null;

    if ($proxy === '' && $user === null && $pass === null) {
      return '';
    }

    if ($user === null) {
      return $proxy;
    }

    $out = $proxy . '@' . $user;
    if ($pass !== null) {
      $out .= ':' . $pass;
    }
    return $out;
  }
}
