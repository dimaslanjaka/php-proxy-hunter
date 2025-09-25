/**
 * Proxy table data class
 *
 * @example
 * const p = new ProxyData({
 *   proxy: '1.2.3.4:8080',
 *   country: 'US',
 *   https: 'true'
 * });
 */
export default class ProxyData {
  /**
   * @param {Object} params
   * @param {string} params.proxy
   * @param {string|null} [params.latency=null]
   * @param {string|null} [params.type=null]
   * @param {string|null} [params.region=null]
   * @param {string|null} [params.city=null]
   * @param {string|null} [params.country=null]
   * @param {string|null} [params.last_check=null]
   * @param {string|null} [params.anonymity=null]
   * @param {string|null} [params.status=null]
   * @param {string|null} [params.timezone=null]
   * @param {string|null} [params.longitude=null]
   * @param {string|null} [params.private=null]
   * @param {string|null} [params.latitude=null]
   * @param {string|null} [params.lang=null]
   * @param {string|null} [params.useragent=null]
   * @param {string|null} [params.webgl_vendor=null]
   * @param {string|null} [params.webgl_renderer=null]
   * @param {string|null} [params.browser_vendor=null]
   * @param {string|null} [params.username=null]
   * @param {string|null} [params.password=null]
   * @param {number|null} [params.id=null]
   * @param {string|null} [params.https='false'] - kept as string to mirror original PHP value
   */
  constructor({
    proxy,
    latency = null,
    type = null,
    region = null,
    city = null,
    country = null,
    last_check = null,
    anonymity = null,
    status = null,
    timezone = null,
    longitude = null,
    private: isPrivate = null,
    latitude = null,
    lang = null,
    useragent = null,
    webgl_vendor = null,
    webgl_renderer = null,
    browser_vendor = null,
    username = null,
    password = null,
    id = null,
    https = 'false'
  } = {}) {
    /** @type {number|null} */
    this.id = id;

    /** @type {string} */
    this.proxy = proxy;

    /** @type {string|null} */
    this.latency = latency;

    /** @type {string|null} */
    this.type = type;

    /** @type {string|null} */
    this.region = region;

    /** @type {string|null} */
    this.city = city;

    /** @type {string|null} */
    this.country = country;

    /** @type {string|null} */
    this.last_check = last_check;

    /** @type {string|null} */
    this.anonymity = anonymity;

    /** @type {string|null} */
    this.status = status;

    /** @type {string|null} */
    this.timezone = timezone;

    /** @type {string|null} */
    this.longitude = longitude;

    /**
     * @type {string|null}
     * Note: `private` is a reserved word in some contexts; constructor param renamed to `isPrivate`.
     */
    this.private = isPrivate;

    /** @type {string|null} */
    this.latitude = latitude;

    /** @type {string|null} */
    this.lang = lang;

    /** @type {string|null} */
    this.useragent = useragent;

    /** @type {string|null} */
    this.webgl_vendor = webgl_vendor;

    /** @type {string|null} */
    this.webgl_renderer = webgl_renderer;

    /** @type {string|null} */
    this.browser_vendor = browser_vendor;

    /** @type {string|null} */
    this.username = username;

    /** @type {string|null} */
    this.password = password;

    /** @type {string|null} */
    this.https = https;
  }

  /**
   * Return a plain object representation (useful for JSON serialization).
   * @returns {Object}
   */
  toObject() {
    return {
      id: this.id,
      proxy: this.proxy,
      latency: this.latency,
      type: this.type,
      region: this.region,
      city: this.city,
      country: this.country,
      last_check: this.last_check,
      anonymity: this.anonymity,
      status: this.status,
      timezone: this.timezone,
      longitude: this.longitude,
      private: this.private,
      latitude: this.latitude,
      lang: this.lang,
      useragent: this.useragent,
      webgl_vendor: this.webgl_vendor,
      webgl_renderer: this.webgl_renderer,
      browser_vendor: this.browser_vendor,
      username: this.username,
      password: this.password,
      https: this.https
    };
  }

  /**
   * JSON.stringify support
   * @returns {Object}
   */
  toJSON() {
    return this.toObject();
  }
}
