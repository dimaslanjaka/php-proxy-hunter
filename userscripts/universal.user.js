// ==UserScript==
// @name         universal proxy parser
// @namespace    dimaslanjaka:universal-parser-proxy
// @version      1.3
// @description  parse proxy from site page
// @author       dimaslanjaka
// @supportURL   https://github.com/dimaslanjaka/php-proxy-hunter/issues
// @homepageURL         https://dimaslanjaka.github.io/
// @contributionURL     https://github.com/dimaslanjaka/php-proxy-hunter
// @license             MIT
// @match        *://hidemy.name/*/proxy-list/*
// @match        *://hidemy.io/*/proxy-list/*
// @match        *://hide.mn/*/proxy-list/*
// @match        *://hidemyna.me/*/proxy-list/*
// @match        *://hidemyname.io/*/proxy-list/*
// @match        *://*.sslproxies.org/*
// @match        *://*.socks-proxy.net/*
// @match        *://*.us-proxy.org/*
// @match   		 *://www.proxydocker.com/*
// @match        *://spys.one/*
// @match        *://proxypremium.top/*
// @match        *://proxyscrape.com/*
// @match        *://squidproxyserver.com/*
// @match        *://geonode.com/free-proxy-list
// @match        *://aliveproxy.com/*
// @match        *://free-proxy-list.net/*
// @match        *://free-proxy.cz/*
// @match        *://www.blackhatworld.com/*
// @match        *://www.cool-proxy.net/*
// @match        *://www.cybersyndrome.net/*
// @match        *://www.echolink.org/*
// @match        *://www.gatherproxy.com/*
// @match        *://www.idcloak.com/*
// @match        *://www.ip-adress.com/*
// @match        *://www.mrhinkydink.com/*
// @match        *://www.samair.ru/*
// @match        *://www.ultraproxies.com/*
// @match        *://www.us-proxy.org/*
// @match        *://www.xroxy.com/*
// @match        *://advanced.name/*
// @match        *://api.openproxylist.xyz/*
// @match        *://api.proxyscrape.com/*
// @match        *://apiproxyfree.com/*
// @match        *://cyber-hub.pw/*
// @match        *://free-proxy-list.com/*
// @match        *://hidemy.name/*
// @match        *://list.proxylistplus.com/*
// @match        *://openproxy.space/*
// @match        *://pastebin.com/*
// @match        *://premiumproxy.net/*
// @match        *://premproxy.com/*
// @match        *://proxy-daily.com/*
// @match        *://proxy-list.org/*
// @match        *://proxylist.geonode.com/*
// @match        *://proxyservers.pro/*
// @match        *://raw.githubusercontent.com/*
// @match        *://smallseotools.com/*
// @match        *://spys.me/*
// @match        *://vpnoverview.com/*
// @match        *://www.freeproxy.world/*
// @match        *://www.netzwelt.de/*
// @match        *://www.proxy-list.download/*
// @match        *://www.proxydocker.com/*
// @match        *://www.proxynova.com/*
// @match        *://www.proxyscan.io/*
// @match        *://proxydb.net/*
// @match 			 *://proxyhub.me/*
// @match 			 *://www.ditatompel.com/*
// @match  			 *://iptotal.io/*
// @match  			 *://www.lumiproxy.com/*
// @match  			 *://free.proxy-sale.com/*
// @match  			 *://freeproxylist.cc/*
// @match  			 *://proxy-tools.com/*
// @match  			 *://fineproxy.org/*
// @match  			 *://proxy-spider.com/*
// @match  			 *://vakhov.github.io/fresh-proxy-list*
// @match        *://www.kxdaili.com/*
// @match        *://www.kuaidaili.com/*
// @match        *://www.ip3366.net/*
// @match        *://www.my-proxy.com/*
// @match        *://www.89ip.cn/*
// @match        *://api.proxy-checker.net/*
// @match        *://libernet.uo1.net/*
// @match				 *://proxyelite.info/*
// @require      https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js
// @require      https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js
// @downloadURL https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/universal.user.js
// @updateURL   https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/universal.meta.js
// ==/UserScript==

// https://openuserjs.org/meta/dimaslanjaka/universal_proxy_parser.meta.js

(function () {
    'use strict';

    /******************************************************************************
    Copyright (c) Microsoft Corporation.

    Permission to use, copy, modify, and/or distribute this software for any
    purpose with or without fee is hereby granted.

    THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH
    REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT,
    INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM
    LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR
    OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR
    PERFORMANCE OF THIS SOFTWARE.
    ***************************************************************************** */
    /* global Reflect, Promise, SuppressedError, Symbol, Iterator */

    function __awaiter(thisArg, _arguments, P, generator) {
      function adopt(value) {
        return value instanceof P ? value : new P(function (resolve) {
          resolve(value);
        });
      }
      return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) {
          try {
            step(generator.next(value));
          } catch (e) {
            reject(e);
          }
        }
        function rejected(value) {
          try {
            step(generator["throw"](value));
          } catch (e) {
            reject(e);
          }
        }
        function step(result) {
          result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected);
        }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
      });
    }
    function __generator(thisArg, body) {
      var _ = {
          label: 0,
          sent: function () {
            if (t[0] & 1) throw t[1];
            return t[1];
          },
          trys: [],
          ops: []
        },
        f,
        y,
        t,
        g = Object.create((typeof Iterator === "function" ? Iterator : Object).prototype);
      return g.next = verb(0), g["throw"] = verb(1), g["return"] = verb(2), typeof Symbol === "function" && (g[Symbol.iterator] = function () {
        return this;
      }), g;
      function verb(n) {
        return function (v) {
          return step([n, v]);
        };
      }
      function step(op) {
        if (f) throw new TypeError("Generator is already executing.");
        while (g && (g = 0, op[0] && (_ = 0)), _) try {
          if (f = 1, y && (t = op[0] & 2 ? y["return"] : op[0] ? y["throw"] || ((t = y["return"]) && t.call(y), 0) : y.next) && !(t = t.call(y, op[1])).done) return t;
          if (y = 0, t) op = [op[0] & 2, t.value];
          switch (op[0]) {
            case 0:
            case 1:
              t = op;
              break;
            case 4:
              _.label++;
              return {
                value: op[1],
                done: false
              };
            case 5:
              _.label++;
              y = op[1];
              op = [0];
              continue;
            case 7:
              op = _.ops.pop();
              _.trys.pop();
              continue;
            default:
              if (!(t = _.trys, t = t.length > 0 && t[t.length - 1]) && (op[0] === 6 || op[0] === 2)) {
                _ = 0;
                continue;
              }
              if (op[0] === 3 && (!t || op[1] > t[0] && op[1] < t[3])) {
                _.label = op[1];
                break;
              }
              if (op[0] === 6 && _.label < t[1]) {
                _.label = t[1];
                t = op;
                break;
              }
              if (t && _.label < t[2]) {
                _.label = t[2];
                _.ops.push(op);
                break;
              }
              if (t[2]) _.ops.pop();
              _.trys.pop();
              continue;
          }
          op = body.call(thisArg, _);
        } catch (e) {
          op = [6, e];
          y = 0;
        } finally {
          f = t = 0;
        }
        if (op[0] & 5) throw op[1];
        return {
          value: op[0] ? op[1] : void 0,
          done: true
        };
      }
    }
    typeof SuppressedError === "function" ? SuppressedError : function (error, suppressed, message) {
      var e = new Error(message);
      return e.name = "SuppressedError", e.error = error, e.suppressed = suppressed, e;
    };

    function encryptStr(text) {
      return text.split('').map(function (c, i) {
        return String.fromCharCode(c.charCodeAt(0) + i + 1);
      }).join('');
    }
    function isValidEncryptStr(str) {
      // Regular expression to check if a string is a valid MD5 hash
      var re = /^[a-f0-9]{32}$/i;
      return re.test(str);
    }

    /**
     * Split a string into chunks of lines.
     * @param {string} input - The input string to split.
     * @param {number} linesPerChunk - Maximum number of lines per chunk.
     * @returns {string[]} An array of chunks, where each chunk is a string with up to `linesPerChunk` lines.
     */
    var splitStringByLines = function (input, linesPerChunk) {
      // Split the input string by lines
      var lines = input.split('\n');
      // Initialize an array to hold chunks of lines
      var chunks = [];
      // Loop through lines and group into chunks
      for (var i = 0; i < lines.length; i += linesPerChunk) {
        // Slice the array to get the chunk of lines
        var chunk = lines.slice(i, i + linesPerChunk);
        // Join the chunk back into a single string and push it to the array
        chunks.push(chunk.join('\n'));
      }
      return chunks;
    };

    /**
     * Upload and check proxy.
     * @param dataToSend - The proxy data to send.
     */
    var addProxyFun = function (dataToSend) {
      if (!dataToSend) return;
      if (typeof dataToSend !== 'string') dataToSend = JSON.stringify(dataToSend, null, 2);
      /**
       * Check if the data has already been sent by looking at local storage.
       * @param data - The data to check.
       * @returns True if the data has already been sent.
       */
      var hasDataBeenSent = function (data) {
        var processedData = data || '';
        if (typeof processedData !== 'string') processedData = encryptStr(JSON.stringify(processedData));
        if (!isValidEncryptStr(processedData)) processedData = encryptStr(processedData);
        var sentData = localStorage.getItem('sentData');
        var result = sentData && sentData.includes(processedData);
        console.log(processedData, 'is same', result);
        return result;
      };
      /**
       * Mark data as sent by saving it in local storage.
       * @param data - The data to be marked as sent.
       */
      var markDataAsSent = function (data) {
        // skip null data
        if (!data) return;
        // Check if data has already been sent
        if (!hasDataBeenSent(data)) {
          var processedData = data;
          if (typeof processedData !== 'string') {
            processedData = encryptStr(JSON.stringify(processedData)); // Convert object data to MD5 hash
          }
          if (!isValidEncryptStr(processedData)) {
            processedData = encryptStr(processedData); // Ensure data is in MD5 format
          }
          try {
            var sentData = localStorage.getItem('sentData') || '';
            sentData += processedData + '\n'; // Append the entire data
            localStorage.setItem('sentData', sentData);
          } catch (_e) {
            console.log('RESET LOCAL STORAGE DATA');
            // reset local storage
            localStorage.setItem('sentData', processedData);
          }
        }
      };
      if (hasDataBeenSent(dataToSend)) return;
      var services = [
      // php proxy hunter
      'http://localhost/proxyAdd.php', 'http://localhost/proxyCheckerParallel.php', 'https://sh.webmanajemen.com/proxyAdd.php', 'https://sh.webmanajemen.com/proxyCheckerParallel.php', 'https://sh.webmanajemen.com/php_backend/proxy-add.php',
      // python proxy hunter
      'https://sh.webmanajemen.com:8443/proxy/check', 'https://localhost:4000/proxy/check', 'https://localhost:7000/proxy/check', 'https://localhost:8000/proxy/check'];
      /**
       * Perform fetch with a delay.
       * @param url - The URL to which the fetch request is made.
       * @param dataToSend - The data to be sent in the POST request.
       * @returns A promise that resolves after the fetch completes.
       */
      var fetchWithDelay = function (url, dataToSend) {
        return __awaiter(this, void 0, void 0, function () {
          var response, headers_1, body, data, error_1;
          return __generator(this, function (_a) {
            switch (_a.label) {
              case 0:
                return [4 /*yield*/, new Promise(function (resolve) {
                  setTimeout(resolve, 1000); // 1 second delay
                })];
              case 1:
                _a.sent();
                _a.label = 2;
              case 2:
                _a.trys.push([2, 7,, 8]);
                return [4 /*yield*/, fetch(url, {
                  signal: AbortSignal.timeout(5000),
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Greasemonkey-Script': '1'
                  },
                  body: dataToSend
                })];
              case 3:
                response = _a.sent();
                if (!!response.ok) return [3 /*break*/, 5];
                headers_1 = [];
                response.headers.forEach(function (value, name) {
                  headers_1.push({
                    name: name,
                    value: value
                  });
                });
                return [4 /*yield*/, response.text()];
              case 4:
                body = _a.sent();
                throw {
                  status: response.status + ' ' + response.statusText,
                  message: 'Network response to ' + url + ' was not ok',
                  headers: headers_1,
                  body: body
                };
              case 5:
                return [4 /*yield*/, response.text()];
              case 6:
                data = _a.sent();
                console.log(data);
                return [3 /*break*/, 8];
              case 7:
                error_1 = _a.sent();
                if (error_1.status) throw error_1; // Re-throw network response errors
                throw {
                  message: 'There was a problem with your fetch operation: (' + (error_1.message || error_1) + ')'
                };
              case 8:
                return [2 /*return*/];
            }
          });
        });
      };
      services.forEach(function (url) {
        /**
         * Upload proxy data to the specified service.
         * @param str_data - The proxy data string to upload.
         */
        var do_upload = function (str_data) {
          fetchWithDelay(url, 'proxy=' + encodeURIComponent(str_data)).then(function () {
            return fetchWithDelay(url, 'proxies=' + encodeURIComponent(str_data));
          }).catch(function (error) {
            console.error('Failed to fetch with delay:', error);
          });
        };
        var split_body = splitStringByLines(dataToSend, 100);
        if (url.indexOf('proxyCheckerParallel') === -1) {
          split_body.forEach(do_upload);
        } else {
          var item = split_body[Math.floor(Math.random() * split_body.length)];
          do_upload(item);
        }
        markDataAsSent(dataToSend);
      });
    };

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
    class ProxyData {
      /**
       * @param {Object} params
       * @param {string|null} [params.proxy=null]
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
        @param {string|null} [params.tun2socks=null] - numeric string indicating tun2socks availability/count
       * @param {string|null} [params.classification=null] - proxy classification (e.g. 'residential', 'datacenter', 'mobile', etc.)
       */
      constructor({
        proxy = null,
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
        https = 'false',
        tun2socks = null,
        classification = null
      } = {}) {
        /** @type {number|null} */
        this.id = id;

        /** @type {string|null} */
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

        /** @type {string|null} */
        this.tun2socks = tun2socks;

        /** @type {string|null} */
        this.classification = classification;
      }

      /**
       * Return a plain object representation (useful for JSON serialization).
       * @returns {Record<string, any>}
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
          https: this.https,
          tun2socks: this.tun2socks
        };
      }

      /**
       * JSON.stringify support
       * @returns {Object}
       */
      toJSON() {
        return this.toObject();
      }
      toString() {
        return JSON.stringify(this.toObject());
      }

      /**
       * Format proxy as a string, including auth if present.
       * e.g. 'username:password@ip:port' or 'ip:port'
       * @returns {string} Formatted proxy string
       */
      format() {
        if (this.username && this.password) {
          return `${this.username}:${this.password}@${this.proxy}`;
        } else {
          return this.proxy;
        }
      }
    }

    /**
     * Validates a proxy string.
     *
     * @param {string|null} proxy - The proxy string to validate.
     * @param {boolean} [validateCredential=false] - Whether to validate credentials if present.
     * @returns {boolean} - True if the proxy is valid, False otherwise.
     */
    function isValidProxy(proxy, validateCredential = false) {
      if (!proxy) {
        return false;
      }

      // Handle credentials if present
      const hasCredential = proxy.includes('@');
      if (hasCredential) {
        try {
          let [proxyPart, credential] = proxy.trim().split('@', 2);
          proxy = proxyPart;
          let [username, password] = credential.trim().split(':');
          if (validateCredential && (!username || !password)) {
            return false;
          }
        } catch (_err) {
          return false; // Invalid credentials format
        }
      }

      // Extract IP address and port
      const parts = proxy.trim().split(':', 2);
      if (parts.length !== 2) {
        return false;
      }
      const [ip, port] = parts;

      // Validate IP address (using provided function)
      if (!isValidIp(ip) || !isValidPort(port)) return false;

      // Validate port number
      const portInt = parseInt(port, 10);
      if (isNaN(portInt) || portInt < 1 || portInt > 65535) {
        return false;
      }

      // Check if the proxy string length is appropriate (if applicable)
      const proxyLength = proxy.length;
      if (proxyLength < 7 || proxyLength > 21) {
        // Adjust based on valid range
        return false;
      }
      return true;
    }

    /**
     * Validates if the given IP address is in a valid format.
     * @param {string} ip The IP address to validate.
     * @returns {boolean} True if the IP address is valid, otherwise false.
     */
    function isValidIp(ip) {
      const ipPattern = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
      return ipPattern.test(ip);
    }

    /**
     * Validates if the given port number is a valid integer within the valid range.
     *
     * @param {number|string} port - The port number to validate, can be a number or a string.
     * @returns {boolean} - Returns true if the port is valid, otherwise false.
     */
    function isValidPort(port) {
      const parsedPort = Number(port); // Parse the input as a number

      // Check if the parsed value is NaN or out of range
      return !isNaN(parsedPort) && parsedPort >= 0 && parsedPort <= 65535;
    }

    /**
     * Extracts proxies from a string and returns an array of ProxyData instances.
     *
     * Supports multiple input formats:
     * - ip:port
     * - ip:port@username:password
     * - username:password@ip:port
     * - whitespace-separated ip and port pairs
     * - JSON snippets containing "ip" and "port" fields
     *
     * The function attempts to validate and deduplicate results. When a proxy with
     * credentials is found, the implementation prefers the entry that contains
     * credentials for the same ip:port.
     *
     * @param {string|null} string - The input string containing proxies in various formats.
     * @returns {ProxyData[]} Array of `ProxyData` objects. Each `ProxyData` will have
     *  - `proxy` (string): the `ip:port` address
     *  - `username` (string|undefined): extracted username if present
     *  - `password` (string|undefined): extracted password if present
     *
     * Entries that cannot be parsed into a valid proxy are omitted.
     */
    function extractProxies(string) {
      if (!string || !string.trim()) return [];
      // We'll build normalized entries with shape: { proxy: 'ip:port', username?: 'u', password?: 'p' }
      const entries = [];
      const ipPortPattern = /((?:(?:\d{1,3}\.){3}\d{1,3}):\d{2,5}(?:@\w+:\w+)?|(?:\w+:\w+@(?:\d{1,3}\.){3}\d{1,3}:\d{2,5}))/g;
      const matches1 = string.match(ipPortPattern) || [];
      const ipPortWhitespacePattern = /((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s+((?!0)\d{2,5})/g;
      const matches2 = Array.from(string.matchAll(ipPortWhitespacePattern));
      const ipPortJsonPattern = /"ip"\s*:\s*"((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})"\s*,\s*"port"\s*:\s*"((?!0)\d{2,5})"/g;
      const matches3 = Array.from(string.matchAll(ipPortJsonPattern));

      // Extract potential user/pass from surrounding JSON
      const userMatch = string.match(/"user"\s*:\s*"([^"]+)"/);
      const passMatch = string.match(/"pass"\s*:\s*"([^"]+)"/);
      const jsonUser = userMatch ? userMatch[1] : undefined;
      const jsonPass = passMatch ? passMatch[1] : undefined;

      // process matches1 (strings)
      matches1.forEach(m => {
        if (m.includes('@')) {
          const parts = m.split('@');
          const left = parts[0];
          const right = parts[1];
          if (isValidProxy(left)) {
            // ip:port@user:pass
            const [username, password] = right.split(':');
            entries.push({
              proxy: left,
              username,
              password
            });
          } else if (isValidProxy(right)) {
            // user:pass@ip:port
            const [username, password] = left.split(':');
            entries.push({
              proxy: right,
              username,
              password
            });
          } else {
            entries.push({
              proxy: m
            });
          }
        } else {
          entries.push({
            proxy: m
          });
        }
      });

      // process whitespace matches
      matches2.forEach(m => {
        // m[1] = ip, m[2] = port
        if (m && m[1] && m[2]) {
          entries.push({
            proxy: `${m[1]}:${m[2]}`
          });
        }
      });

      // process json matches and attach json user/pass if present
      matches3.forEach(m => {
        if (m && m[1] && m[2]) {
          entries.push({
            proxy: `${m[1]}:${m[2]}`,
            username: jsonUser,
            password: jsonPass
          });
        }
      });

      // Deduplicate, prioritizing entries that have credentials
      const map = new Map();
      entries.forEach(e => {
        const key = e.proxy;
        if (!key) return;
        if (!isValidProxy(key)) return;
        const existing = map.get(key);
        if (!existing) {
          map.set(key, e);
        } else {
          const existingHasCreds = existing.username && existing.password;
          const newHasCreds = e.username && e.password;
          if (!existingHasCreds && newHasCreds) {
            map.set(key, e);
          }
        }
      });

      // Convert to ProxyData instances
      return Array.from(map.values()).map(e => {
        const pd = new ProxyData();
        pd.proxy = e.proxy;
        if (e.username) pd.username = e.username;
        if (e.password) pd.password = e.password;
        return pd;
      });
    }

    /**
     * Extracts IP:PORT pairs from a given input string.
     *
     * @param input - The input string containing IP:PORT pairs.
     * @returns An array of IP:PORT pairs found in the input string.
     */
    var extractIpPortPairs = function (input) {
      if (!input) return [];
      var regex = /(?:[0-9]{1,3}\.){3}[0-9]{1,3}:[0-9]{1,5}/g;
      return input.match(regex) || [];
    };

    /**
     * Extracts unique IP:PORT pairs from the body and specific elements in the DOM.
     *
     * @returns A promise that resolves with an array of unique IP:PORT objects.
     */
    var extractIpPortFromBody = function () {
      var result = [];
      var area = document.querySelectorAll('textarea,td');
      result.push.apply(result, extractIpPortPairs(document.body.innerHTML));
      area.forEach(function (el) {
        result.push.apply(result, extractIpPortPairs(el.value || ''));
      });
      var divList = document.querySelectorAll('div.list');
      divList.forEach(function (el) {
        result.push.apply(result, extractIpPortPairs(el.innerHTML));
      });
      var unique = result.filter(function (str, index, self) {
        return index === self.findIndex(function (t) {
          return t === str;
        });
      });
      var map = unique.map(function (str) {
        return {
          raw: str
        };
      });
      return Promise.resolve(map);
    };

    /**
     * Extract IP addresses from a string.
     * @param str - The string to search.
     * @returns The list of IPv4 addresses found in the input string.
     */
    var findIPv4Addresses = function (str) {
      var ipv4Pattern = /\b(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/g;
      return str.match(ipv4Pattern) || [];
    };

    /**
     * free.proxy-sale.com parser
     * * extract only IP
     * @returns {Promise<{ raw: string }[]>}
     */
    var freeProxySale = function () {
      return new Promise(function (resolve) {
        var result = [];
        var proxyTable = document.querySelectorAll('.proxy__table');
        proxyTable.forEach(function (wrapper) {
          Array.from(wrapper.querySelectorAll('[class^=css-]')).forEach(function (el) {
            var ips = findIPv4Addresses(el.textContent || '');
            if (ips.length > 0) {
              ips.forEach(function (ip) {
                result.push({
                  raw: ip + ':80'
                });
                result.push({
                  raw: ip + ':443'
                });
                result.push({
                  raw: ip + ':8080'
                });
                result.push({
                  raw: ip + ':8000'
                });
              });
            }
          });
        });
        resolve(result);
      });
    };

    /**
     * Function to parse the first and second row proxy data from a table.
     * @returns {Promise<any[]>} - A promise that resolves with an array of proxy data objects.
     */
    var parse_first_and_second_row = function () {
      return new Promise(function (resolve) {
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));
        var ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
        var objectWrapper = [];
        for (var i = 0; i < tables.length; i++) {
          var table = tables[i];
          var rows = Array.prototype.slice.call(table.querySelectorAll('tr'));
          for (var j = 0; j < rows.length; j++) {
            var row = rows[j];
            var buildObject = {
              raw: null,
              code: null,
              anonymity: null,
              ssl: null,
              google: null,
              alert: null,
              type: 'http',
              test: null
            };
            var td = row.querySelectorAll('td');
            var proxy = td[0];
            var port = td[1];
            var countryCode = td[2];
            var anonymity = td[4];
            var google_1 = td[5];
            var ssl = td[6];
            if (proxy && ssl && ipOnly.test(proxy.innerText)) {
              buildObject.raw = proxy.innerText.trim() + ':' + port.innerText.trim();
              buildObject.google = /^yes/.test(google_1.innerText.trim()) ? true : false;
              buildObject.ssl = /^yes/.test(ssl.innerText.trim()) ? true : false;
              buildObject.code = countryCode.innerText.trim();
              switch (anonymity.innerText.trim()) {
                case 'elite proxy':
                  buildObject.anonymity = 'H';
                  break;
                case 'anonymous':
                  buildObject.anonymity = 'A';
                  break;
                default:
                  buildObject.anonymity = 'N';
                  break;
              }
              objectWrapper.push(buildObject);
            }
          }
        }
        resolve(objectWrapper);
      });
    };

    /**
     * Function to parse IP:PORT from the first row.
     * @returns {Promise<any[]>} - A promise that resolves with an array of IP:PORT data objects.
     */
    var parse_first_row_ip_port = function () {
      return new Promise(function (resolve) {
        var regex = /^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})$/;
        var result = [];
        var spy14Elements = Array.prototype.slice.call(document.getElementsByClassName('spy14'));
        for (var i = 0; i < spy14Elements.length; i++) {
          if (spy14Elements[i].innerText.includes(':')) {
            result.push({
              raw: spy14Elements[i].innerText
            });
          }
        }
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));
        for (var j = 0; j < tables.length; j++) {
          var table = tables[j];
          var trElements = Array.prototype.slice.call(table.querySelectorAll('tr'));
          for (var k = 0; k < trElements.length; k++) {
            var tdElements = Array.prototype.slice.call(trElements[k].querySelectorAll('td'));
            if (tdElements.length > 0 && regex.test(tdElements[0].innerText)) {
              result.push({
                raw: tdElements[0].innerText
              });
            }
          }
        }
        resolve(result);
      });
    };

    /**
     * Function to parse HideMe proxy data.
     * @returns A promise that resolves with an array of proxy data objects.
     */
    var parse_hideme = function () {
      return new Promise(function (resolve) {
        var result = [];
        var rows = document.querySelectorAll('.table_block > table > tbody > tr');
        rows.forEach(function (row) {
          var _a, _b, _c, _d;
          var tdList = row.querySelectorAll('td');
          var host = (_b = (_a = tdList[0]) === null || _a === void 0 ? void 0 : _a.textContent) === null || _b === void 0 ? void 0 : _b.trim();
          var port = (_d = (_c = tdList[1]) === null || _c === void 0 ? void 0 : _c.textContent) === null || _d === void 0 ? void 0 : _d.trim();
          if (host && port) {
            result.push({
              raw: "".concat(host, ":").concat(port)
            });
          }
        });
        resolve(result);
      });
    };

    /**
     * Function to parse proxy data from a table.
     * @returns {Promise<any[]>} - A promise that resolves with an array of proxy data objects.
     */
    var parse_prem_proxy = function () {
      return new Promise(function (resolve) {
        // Select all table elements on the page
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));
        var ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
        var objectWrapper = [];
        // Loop through each table element using a for loop
        for (var i = 0; i < tables.length; i++) {
          var table = tables[i];
          var rows = Array.prototype.slice.call(table.querySelectorAll('tr'));
          for (var j = 0; j < rows.length; j++) {
            var row = rows[j];
            var td = Array.prototype.slice.call(row.querySelectorAll('td'));
            var texts = td.map(function (el) {
              return el.innerText;
            }).filter(function (str) {
              return typeof str === 'string' && str.trim().length > 0;
            });
            if (ipOnly.test(texts.join(' '))) {
              objectWrapper.push({
                raw: texts[0],
                ip: texts[0].split(':')[0],
                port: texts[0].split(':')[1],
                type: texts[2],
                country: texts[3],
                anonymity: texts[4],
                https: texts[5]
              });
            }
          }
        }
        resolve(objectWrapper);
      });
    };

    /**
     * Function to parse proxy data from the document.
     * @returns A promise that resolves with an array of proxy data objects.
     */
    function parse_proxy_db_net() {
      return new Promise(function (resolve) {
        var regex = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{2,5})/;
        var result = [];
        var a = Array.prototype.slice.call(document.getElementsByClassName('spy14'));
        for (var outerLoopIndex = 0; outerLoopIndex < a.length; outerLoopIndex++) {
          // Renamed outer loop variable
          if (a[outerLoopIndex].innerText.includes(':')) {
            result.push({
              raw: a[outerLoopIndex].innerText
            });
          }
        }
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));
        for (var tableLoopIndex = 0; tableLoopIndex < tables.length; tableLoopIndex++) {
          // Renamed outer loop variable for tables
          var table = tables[tableLoopIndex];
          var tr = Array.prototype.slice.call(table.querySelectorAll('tr'));
          for (var i = 0; i < tr.length; i++) {
            // Inner loop variable remains i
            var td = Array.prototype.slice.call(tr[i].querySelectorAll('td'));
            if (td[0]) {
              var test_1 = regex.test(td[0].innerText);
              if (test_1) result.push({
                raw: td[0].innerText
              });
            }
          }
        }
        resolve(result);
      });
    }

    /**
     * Function to parse proxy data from a table.
     * @returns {Promise<any[]>} - A promise that resolves with an array of proxy data objects.
     */
    var parse_proxylistplus = function () {
      return new Promise(function (resolve) {
        // Select all table elements on the page
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));
        var ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
        var objectWrapper = [];
        // Loop through each table element using a for loop
        for (var i = 0; i < tables.length; i++) {
          var table = tables[i];
          var rows = Array.prototype.slice.call(table.querySelectorAll('tr'));
          for (var j = 0; j < rows.length; j++) {
            var row = rows[j];
            var td = Array.prototype.slice.call(row.querySelectorAll('td'));
            var texts = td.map(function (el) {
              return el.innerText;
            }).filter(function (str) {
              return typeof str === 'string' && str.trim().length > 0;
            });
            if (ipOnly.test(texts.join(' '))) {
              var item = {
                raw: texts[0] + ':' + texts[1],
                ip: texts[0],
                port: texts[1],
                type: texts[2],
                country: texts[3],
                anonymity: texts[4],
                https: texts[5]
              };
              objectWrapper.push(item);
            }
          }
        }
        resolve(objectWrapper);
      });
    };

    /**
     * Function to parse the second and third row proxy data from a table.
     * @returns {Promise<any[]>} - A promise that resolves with an array of proxy data objects.
     */
    var parse_second_and_third_row = function () {
      return new Promise(function (resolve) {
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));
        var ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
        var objectWrapper = [];
        for (var i = 0; i < tables.length; i++) {
          var table = tables[i];
          var rows = Array.prototype.slice.call(table.querySelectorAll('tr'));
          for (var j = 0; j < rows.length; j++) {
            var row = rows[j];
            var td = Array.prototype.slice.call(row.querySelectorAll('td'));
            var texts = td.map(function (el) {
              return el.innerText;
            }).filter(function (str) {
              return typeof str === 'string' && str.trim().length > 0;
            });
            if (ipOnly.test(texts.join(' '))) {
              objectWrapper.push({
                raw: texts[1] + ':' + texts[2],
                ip: texts[0],
                port: texts[1],
                type: texts[2],
                country: texts[3],
                anonymity: texts[4],
                https: texts[5]
              });
            }
          }
        }
        resolve(objectWrapper);
      });
    };

    /**
     * Parses proxy information from multiple sources.
     * Returns a promise that resolves with a string containing valid IP:PORT combinations.
     *
     * @returns A promise that resolves with a string of valid proxy addresses.
     */
    var parse_all = function () {
      return new Promise(function (resolve) {
        /**
         * @type {Promise<{ raw: string }[]>[]}
         */
        var all = [freeProxySale(), parse_first_and_second_row(), parse_hideme(), parse_first_row_ip_port(), parse_second_and_third_row(), parse_proxylistplus(), parse_prem_proxy(), parse_proxy_db_net(), extractIpPortFromBody()];
        Promise.all(all).then(function (results) {
          var flat = results.flat().filter(function (item) {
            if (!item) return false;
            var str = typeof item === 'string' ? item : JSON.stringify(item);
            var regex = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/gm;
            return regex.test(str);
          });
          var additionalItems = [];
          var mappedItems = flat.map(function (item) {
            var valid = false;
            var regex_ip = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/gm;
            var regex_port = /(\d{1,5})/gm;
            var regex_proxy = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/gm;
            if (typeof item === 'object') {
              if (item.raw) {
                valid = regex_proxy.test(item.raw);
              }
              if (!valid) {
                if (item.ip) {
                  if (regex_proxy.test(item.ip)) {
                    item.raw = item.ip;
                    item.ip = item.raw.split(':')[0];
                  }
                }
              }
              var no_more_than_21 = false;
              if (item.raw.length > 21) {
                no_more_than_21 = true;
                var extract = extractProxies(item.raw);
                if (extract.length > 0) {
                  for (var i = 0; i < extract.length; i++) {
                    var ex = extract[i];
                    if (i === 0) {
                      item.raw = ex.proxy;
                    } else {
                      additionalItems.push({
                        raw: ex.proxy || ''
                      });
                    }
                  }
                }
              }
              if (item.raw && !no_more_than_21) {
                var split = item.raw.split(':');
                var build_proxy_1 = [];
                if (split.length > 1) {
                  split.forEach(function (str) {
                    if (regex_ip.test(str)) {
                      build_proxy_1[0] = str;
                    } else if (regex_port.test(str)) {
                      build_proxy_1[1] = str;
                    }
                  });
                  if (regex_proxy.test(build_proxy_1.join(':'))) {
                    item.raw = build_proxy_1.join(':');
                  } else if (!regex_proxy.test(item.raw)) {
                    console.error(item.raw, 'invalid regex_proxy');
                    return {
                      raw: ''
                    };
                  }
                }
              }
            }
            return item;
          });
          var filteredItems = mappedItems.filter(function (item) {
            return item && item.raw.length > 0 && item.raw.length <= 21;
          });
          var uniqueItems = filteredItems.concat(additionalItems).filter(function (obj, index, self) {
            return index === self.findIndex(function (t) {
              return t.raw === obj.raw;
            });
          });
          var build = '';
          for (var i = 0; i < uniqueItems.length; i++) {
            var item = uniqueItems[i];
            if (build.indexOf(item.raw) === -1) {
              build += item.raw + '\n';
            }
          }
          resolve(build);
        }).catch(function (error) {
          console.error(error);
          resolve('<empty proxies>');
        });
      });
    };

    function createButton() {
      var btn = document.createElement('button');
      btn.id = 'php-proxy-hunter-grab-proxy';
      btn.setAttribute('style', 'position: fixed; top: 50%; left: 0; transform: translateY(-50%); opacity: 0.6; margin-left: 1.2em; color: white; background-color: black; z-index: 9999; border: none; padding: 0.5em 1em; cursor: pointer; font-size: 14px; border-radius: 4px; transition: opacity 0.3s ease;');
      btn.innerText = 'PARSE PROXIES';
      btn.classList.add('btn', 'button', 'btn-primary');
      btn.onclick = function () {
        parse_all().then(function (result) {
          console.log('Parsed Proxies:', result);
          addProxyFun(result);
          var htmlContent = "\n<!DOCTYPE html>\n<html>\n<head>\n  <title>JSON Data</title>\n  <style>\n    body {\n      background-color: #121212;\n      color: #ffffff;\n      font-family: Arial, sans-serif;\n      padding: 20px;\n    }\n    pre {\n      white-space: pre-wrap; /* Ensures long lines wrap */\n      word-wrap: break-word; /* Prevents overflowing */\n    }\n  </style>\n</head>\n<body><pre>".concat(result.trim(), "</pre></body>\n</html>");
          window.open(URL.createObjectURL(new Blob([htmlContent], {
            type: 'text/html'
          })), 'width=800,height=600');
        }).catch(function (error) {
          console.error(error);
        });
      };
      document.body.appendChild(btn);
    }

    (function () {

      /**
       * Sanitizes HTML by removing specified tags and the style attribute.
       * @param html - The HTML content to sanitize.
       * @returns The sanitized HTML content.
       */
      var sanitizeHtml = function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        // Tags to remove
        var tagsToRemove = ['img', 'script', 'iframe', 'link', 'ins'];
        tagsToRemove.forEach(function (tagName) {
          var _a;
          var tags = doc.getElementsByTagName(tagName);
          for (var i = tags.length - 1; i >= 0; i--) {
            (_a = tags[i].parentNode) === null || _a === void 0 ? void 0 : _a.removeChild(tags[i]);
          }
        });
        // Remove 'style' attribute from all tags
        var allTags = doc.getElementsByTagName('*');
        for (var i = 0; i < allTags.length; i++) {
          allTags[i].removeAttribute('style');
        }
        var filteredHtml = doc.documentElement.outerHTML;
        var doc2 = new DOMParser().parseFromString(filteredHtml, 'text/html');
        var elements = [];
        doc2.querySelectorAll('textarea,table,.list').forEach(function (el) {
          elements.push(el.outerHTML);
        });
        return elements.join('\n');
      };
      /**
       * Monitors changes to the body's HTML content and performs actions when changes are detected.
       */
      var monitorBodyChanges = function () {
        var lastHtml = '';
        setInterval(function () {
          var currentHtml = document.body.innerHTML;
          var sanitizedHtml = sanitizeHtml(currentHtml);
          if (sanitizedHtml !== lastHtml) {
            lastHtml = sanitizedHtml;
            console.log('body changed');
            parse_all().then(addProxyFun);
          }
        }, 3000); // Check every 3 seconds
      };
      setTimeout(monitorBodyChanges, 3000);
      setTimeout(createButton, 5000);
    })();

})();
