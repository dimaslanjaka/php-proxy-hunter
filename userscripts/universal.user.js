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

  const md5 = function (text) {
    // eslint-disable-next-line no-undef
    return CryptoJS.MD5(text).toString();
  };

  const isMD5Format = function (str) {
    // Regular expression to check if a string is a valid MD5 hash
    const md5Regex = /^[a-f0-9]{32}$/i;
    return md5Regex.test(str);
  };

  /**
   * Split a string into chunks of lines.
   * @param {string} input - The input string to split.
   * @param {number} linesPerChunk - Maximum number of lines per chunk.
   * @returns {string[]} An array of chunks, where each chunk is a string with up to `linesPerChunk` lines.
   */
  const splitStringByLines = function (input, linesPerChunk) {
    // Split the input string by lines
    const lines = input.split('\n');

    // Initialize an array to hold chunks of lines
    const chunks = [];

    // Loop through lines and group into chunks
    for (let i = 0; i < lines.length; i += linesPerChunk) {
      // Slice the array to get the chunk of lines
      const chunk = lines.slice(i, i + linesPerChunk);

      // Join the chunk back into a single string and push it to the array
      chunks.push(chunk.join('\n'));
    }

    return chunks;
  };

  /**
   * Upload and check proxy.
   * @param {string} dataToSend - The proxy data to send.
   */
  var addProxyFun = function (dataToSend) {
    if (!dataToSend) return;
    if (typeof dataToSend !== 'string') dataToSend = JSON.stringify(dataToSend, null, 2);

    /**
     * Check if the data has already been sent by looking at local storage.
     * @param {string|Object} data - The data to check.
     * @returns {boolean} True if the data has already been sent.
     */
    var hasDataBeenSent = function (data) {
      if (typeof data !== 'string') data = md5(JSON.stringify(data));
      if (!isMD5Format(data)) data = md5(data);
      var sentData = localStorage.getItem('sentData');
      var result = sentData && sentData.includes(data);
      console.log(data, 'is same', result);
      return result;
    };

    /**
     * Mark data as sent by saving it in local storage.
     * @param {string|Object} data - The data to be marked as sent.
     */
    var markDataAsSent = function (data) {
      // skip null data
      if (!data) return;

      // Check if data has already been sent
      if (!hasDataBeenSent(data)) {
        if (typeof data !== 'string') {
          data = md5(JSON.stringify(data)); // Convert object data to MD5 hash
        }
        if (!isMD5Format(data)) {
          data = md5(data); // Ensure data is in MD5 format
        }

        try {
          var sentData = localStorage.getItem('sentData') || '';
          sentData += data + '\n'; // Append the entire data
          localStorage.setItem('sentData', sentData);
        } catch (_e) {
          console.log('RESET LOCAL STORAGE DATA');
          // reset local storage
          localStorage.setItem('sentData', data);
        }
      }
    };

    if (hasDataBeenSent(dataToSend)) return;

    var services = [
      // php proxy hunter
      'http://localhost/proxyAdd.php',
      'http://localhost/proxyCheckerParallel.php',
      'https://sh.webmanajemen.com/proxyAdd.php',
      'https://sh.webmanajemen.com/proxyCheckerParallel.php',
      // python proxy hunter
      'https://sh.webmanajemen.com:8443/proxy/check',
      'https://localhost:4000/proxy/check',
      'https://localhost:7000/proxy/check',
      'https://localhost:8000/proxy/check'
    ];

    /**
     * Perform fetch with a delay.
     * @param {string} url - The URL to which the fetch request is made.
     * @param {string} dataToSend - The data to be sent in the POST request.
     * @returns {Promise} - Returns a promise that resolves after the fetch completes.
     */
    var fetchWithDelay = function (url, dataToSend) {
      return new Promise(function (resolve, reject) {
        setTimeout(function () {
          fetch(url, {
            signal: AbortSignal.timeout(5000),
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Greasemonkey-Script': '1' },
            body: dataToSend
          })
            .then(function (response) {
              if (!response.ok) {
                // Log all response headers
                var headers = [];
                response.headers.forEach(function (value, name) {
                  headers.push({ name: name, value: value });
                });
                return response.text().then(function (body) {
                  return reject({
                    status: response.status + ' ' + response.statusText,
                    message: 'Network response to ' + url + ' was not ok',
                    headers: headers,
                    body: body
                  });
                });
              }
              return response.text();
            })
            .then(function (data) {
              console.log(data);
              resolve();
            })
            .catch(function (error) {
              reject({
                message: 'There was a problem with your fetch operation: (' + error.message + ')'
              });
            });
        }, 1000); // 1 second delay
      });
    };

    // Assuming services and splitStringByLines are already defined
    services.forEach(function (url) {
      var do_upload = function (str_data) {
        fetchWithDelay(url, 'proxy=' + encodeURIComponent(str_data))
          .then(function () {
            return fetchWithDelay(url, 'proxies=' + encodeURIComponent(str_data));
          })
          .catch(function (error) {
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
   * Function to parse proxy data from the document.
   * @returns {Promise} - A promise that resolves with an array of proxy data objects.
   */
  var parse_proxy_db_net = function () {
    return new Promise(function (resolve) {
      var regex = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{2,5})/;
      var result = [];
      var a = Array.prototype.slice.call(document.getElementsByClassName('spy14'));

      for (var outerLoopIndex = 0; outerLoopIndex < a.length; outerLoopIndex++) {
        // Renamed outer loop variable
        if (a[outerLoopIndex].innerText.includes(':')) {
          result.push({ raw: a[outerLoopIndex].innerText });
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
            var test = regex.test(td[0].innerText);
            if (test) result.push({ raw: td[0].innerText });
          }
        }
      }

      resolve(result);
    });
  };

  /**
   * Function to parse HideMe proxy data.
   * @returns {Promise<any[]>} - A promise that resolves with an array of proxy data objects.
   */
  var parse_hideme_jquery = function () {
    return new Promise(function (resolve) {
      var result = [];
      $('.table_block>table>tbody>tr').each(function (i, e) {
        var tr = $(e);
        var tdList = tr.children('td');
        var host = tdList.get(0).innerText;
        var port = tdList.get(1).innerText;
        result.push({ raw: host + ':' + port });
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
          var texts = td
            .map(function (el) {
              return el.innerText;
            })
            .filter(function (str) {
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
          var texts = td
            .map(function (el) {
              return el.innerText;
            })
            .filter(function (str) {
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

  // parse_proxylistplus().then(console.log);

  /**
   * Function to parse the second and third row proxy data from a table.
   * @returns {Promise<any[]>} - A promise that resolves with an array of proxy data objects.
   */
  var parse_second_and_third_row = function () {
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
          var texts = td
            .map(function (el) {
              return el.innerText;
            })
            .filter(function (str) {
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
   * Function to parse the first and second row proxy data from a table.
   * @returns {Promise<any[]>} - A promise that resolves with an array of proxy data objects.
   */
  var parse_first_and_second_row = function () {
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
          var google = td[5];
          var ssl = td[6];

          if (proxy && ssl && ipOnly.test(proxy.innerText)) {
            buildObject.raw = proxy.innerText.trim() + ':' + port.innerText.trim();
            buildObject.google = /^yes/.test(google.innerText.trim()) ? true : false;
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
          result.push({ raw: spy14Elements[i].innerText });
        }
      }

      var tables = Array.prototype.slice.call(document.querySelectorAll('table'));
      for (var j = 0; j < tables.length; j++) {
        var table = tables[j];
        var trElements = Array.prototype.slice.call(table.querySelectorAll('tr'));

        for (var k = 0; k < trElements.length; k++) {
          var tdElements = Array.prototype.slice.call(trElements[k].querySelectorAll('td'));
          if (tdElements.length > 0 && regex.test(tdElements[0].innerText)) {
            result.push({ raw: tdElements[0].innerText });
          }
        }
      }

      resolve(result);
    });
  };

  /**
   * Extracts IP:PORT pairs from a given input string.
   *
   * @param {string} input - The input string containing IP:PORT pairs.
   * @returns {string[]} An array of IP:PORT pairs found in the input string.
   */
  var extractIpPortPairs = function (input) {
    if (!input) return [];
    // Regular expression to match IP:PORT
    var regex = /(?:[0-9]{1,3}\.){3}[0-9]{1,3}:[0-9]{1,5}/g;
    return input.match(regex) || [];
  };

  /**
   * Extracts unique IP:PORT pairs from the body and specific elements in the DOM.
   *
   * @returns {Promise<any[]>} A promise that resolves with an array of unique IP:PORT objects.
   */
  var extractIpPortFromBody = function () {
    var result = [];
    var area = document.querySelectorAll('textarea,td');

    // Extract IP:PORT pairs from the body content
    result.push.apply(result, extractIpPortPairs(document.body.innerHTML));

    // Extract IP:PORT pairs from the values of textarea and td elements
    area.forEach(function (el) {
      result.push.apply(result, extractIpPortPairs(el.value));
    });

    // Extract IP:PORT pairs from div elements with class 'list'
    var divList = document.querySelectorAll('div.list');
    divList.forEach(function (el) {
      result.push.apply(result, extractIpPortPairs(el.innerHTML));
    });

    // Remove duplicates by filtering the array
    var unique = result.filter(function (str, index, self) {
      return (
        index ===
        self.findIndex(function (t) {
          return t === str;
        })
      );
    });

    // Map the unique IP:PORT pairs to an object structure
    var map = unique.map(function (str) {
      return { raw: str };
    });

    return Promise.resolve(map);
  };

  /**
   * extract IPs from string
   * @param {string} str
   * @returns
   */
  const findIPv4Addresses = function (str) {
    const ipv4Pattern =
      /\b(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/g;
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
          var ips = findIPv4Addresses(el.textContent);
          if (ips.length > 0) {
            ips.forEach(function (ip) {
              result.push({ raw: ip + ':80' });
              result.push({ raw: ip + ':443' });
              result.push({ raw: ip + ':8080' });
              result.push({ raw: ip + ':8000' });
            });
          }
        });
      });
      resolve(result);
    });
  };

  /**
   * Parses proxy information from multiple sources.
   * Returns a promise that resolves with a string containing valid IP:PORT combinations.
   *
   * @returns {Promise<string>} A promise that resolves with a string of valid proxy addresses.
   */
  var parse_all = function () {
    return new Promise(function (resolve) {
      /**
       * @type {Promise<{ raw: string }[]>[]}
       */
      var all = [
        freeProxySale(),
        parse_first_and_second_row(),
        parse_hideme_jquery(),
        parse_first_row_ip_port(),
        parse_second_and_third_row(),
        parse_proxylistplus(),
        parse_prem_proxy(),
        parse_proxy_db_net(),
        extractIpPortFromBody()
      ];
      Promise.all(all)
        .then(function (results) {
          // flatting
          var flat = results.flat().filter(function (item) {
            if (!item) return false;
            var str = typeof item === 'string' ? item : JSON.stringify(item);
            var regex = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/gm;
            return regex.test(str);
          });
          // remove non IP:PORT
          var additionalItems = [];
          var filteredItems = flat
            .map(function (item) {
              var valid = false;
              var regex_ip = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/gm;
              var regex_port = /(\d{1,5})/gm;
              var regex_proxy = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/gm;
              if (typeof item === 'object') {
                if (item.raw) {
                  // validate proxy is valid
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
                // re-validate string length no more than 21
                var no_more_than_21 = false;
                if (item.raw.length > 21) {
                  no_more_than_21 = true;
                  var extract = extract_proxies(item.raw);
                  if (extract.length > 0) {
                    for (var i = 0; i < extract.length; i++) {
                      var ex = extract[i];
                      if (i === 0) {
                        item.raw = ex.ip + ':' + ex.port;
                      } else {
                        additionalItems.push({ raw: ex.ip + ':' + ex.port });
                      }
                    }
                  }
                }
                if (item.raw && !no_more_than_21) {
                  // fix IP:PORT
                  var split = item.raw.split(':');
                  var build_proxy = [];
                  if (split.length > 1) {
                    split.forEach(function (str) {
                      if (regex_ip.test(str)) {
                        build_proxy[0] = str;
                      } else if (regex_port.test(str)) {
                        build_proxy[1] = str;
                      }
                    });
                    if (regex_proxy.test(build_proxy.join(':'))) {
                      item.raw = build_proxy.join(':');
                    } else if (!regex_proxy.test(item.raw)) {
                      console.error(item.raw, 'invalid regex_proxy');
                      return { raw: '' };
                    }
                  }
                }
              }
              return item;
            })
            .filter(function (item) {
              // validate proxy length
              return item && item.raw.length > 0 && item.raw.length <= 21;
            });
          // unique
          var uniqueItems = [...filteredItems, ...additionalItems].filter(function (obj, index, self) {
            return (
              index ===
              self.findIndex(function (t) {
                return t.raw === obj.raw;
              })
            );
          });
          // build to string
          var build = '';
          for (var i = 0; i < uniqueItems.length; i++) {
            var item = uniqueItems[i];
            if (build.indexOf(item.raw) === -1) {
              build += item.raw + '\n';
            }
          }
          // const result = uniqueArray.map((obj) => JSON.stringify(obj, null, 2)).join("\n");
          resolve(build);
        })
        .catch(function (error) {
          console.error(error);
          resolve('<empty proxies>');
        });
    });
  };

  /**
   * Function to sanitize HTML by removing specified tags and the 'style' attribute.
   * @param {string} html - The HTML content to sanitize.
   * @returns {string} The sanitized HTML content.
   */
  var sanitizeHtml = function (html) {
    var doc = new DOMParser().parseFromString(html, 'text/html');

    // Tags to remove
    var tagsToRemove = ['img', 'script', 'iframe', 'link', 'ins'];

    tagsToRemove.forEach(function (tagName) {
      var tags = doc.getElementsByTagName(tagName);
      for (var i = tags.length - 1; i >= 0; i--) {
        tags[i].parentNode.removeChild(tags[i]);
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

  const btn = document.createElement('button');
  btn.id = 'php-proxy-hunter-grab-proxy';
  btn.setAttribute(
    'style',
    'position: fixed; top: 50%; left: 0; transform: translateY(-50%); opacity: 0.6; margin-left: 1.2em; color: white; background-color: black;'
  );
  btn.innerText = 'PARSE PROXIES';
  btn.classList.add('btn', 'button', 'btn-primary');
  btn.onclick = function () {
    parse_all()
      .then(function (result) {
        addProxyFun(result);
        var htmlContent = `
<!DOCTYPE html>
<html>
<head>
  <title>JSON Data</title>
  <style>
    body {
      background-color: #121212;
      color: #ffffff;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    pre {
      white-space: pre-wrap; /* Ensures long lines wrap */
      word-wrap: break-word; /* Prevents overflowing */
    }
  </style>
</head>
<body><pre>${result.trim()}</pre></body>
</html>`;

        window.open(URL.createObjectURL(new Blob([htmlContent], { type: 'text/html' })), 'width=800,height=600');
      })
      .catch(function (error) {
        console.error(error);
      });
  };
  document.body.appendChild(btn);
})();

/**
 * Extracts IP addresses and ports from a given text.
 * @param {string} text - The multiline text to extract IP:PORT from.
 * @returns {Array<{ip: string, port: string}>} - An array of objects containing IP and port.
 */
function extract_proxies(text) {
  // Regular expression to match IP addresses and ports
  const regex = /(\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b):([0-9]{1,5})/g;

  // Extract IP:PORT matches
  const matches = [];
  let match;
  while ((match = regex.exec(text)) !== null) {
    matches.push({ ip: match[1], port: match[2] });
  }

  return matches;
}
