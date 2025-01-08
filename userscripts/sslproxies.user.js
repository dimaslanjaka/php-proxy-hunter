// ==UserScript==
// @name         sslproxies.org proxy parser
// @namespace    dimaslanjaka:sslproxies-parser-proxy
// @version      1.3
// @description  parse proxy from site page
// @author       dimaslanjaka
// @supportURL   https://github.com/dimaslanjaka/php-proxy-hunter/issues
// @homepageURL         https://dimaslanjaka.github.io/
// @contributionURL     https://github.com/dimaslanjaka/php-proxy-hunter
// @license             MIT
// @match        *://*.sslproxies.org/*
// @match        *://*.socks-proxy.net/*
// @match        *://*.us-proxy.org/*
// @match        *://free-proxy-list.net/*
// @noframes
// @run-at document-end
// @downloadURL  https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/sslproxies.user.js
// @updateURL    https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/sslproxies.meta.js
// ==/UserScript==

(function () {
  'use strict';

  const addProxyFun = (dataToSend) => {
    const url = 'https://sh.webmanajemen.com/proxyAdd.php';
    fetch(url, {
      signal: AbortSignal.timeout(5000),
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `proxies=${encodeURIComponent(dataToSend)}`
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.text();
      })
      .then((data) => {
        console.log(data);
      })
      .catch((error) => {
        console.log('There was a problem with your fetch operation: (' + error.message + ')');
      });
  };

  /**
   * @returns {Promise<any[]>}
   */
  const parse = () => {
    return new Promise((resolve) => {
      // Select all table elements on the page
      const tables = Array.from(document.querySelectorAll('table'));
      const ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
      const objectWrapper = [];

      // Loop through each table element using a for loop
      for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        const rows = Array.from(table.querySelectorAll('tr'));

        for (let j = 0; j < rows.length; j++) {
          const row = rows[j];
          const buildObject = {
            proxy: null,
            code: null,
            anonymity: null,
            ssl: null,
            google: null,
            alert: null,
            type: 'http',
            test: null
          };
          const td = row.querySelectorAll('td');
          const proxy = td[0];
          const port = td[1];
          const countryCode = td[2];
          const anonymity = td[4];
          const google = td[5];
          const ssl = td[6];
          if (proxy && ipOnly.test(proxy.innerText)) {
            // console.log(proxy.innerText, port.innerText, countryCode.innerText, anonymity.innerText, google.innerText, ssl.innerText);
            buildObject.proxy = `${proxy.innerText.trim()}:${port.innerText.trim()}`;
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
      addProxyFun(JSON.stringify(objectWrapper));
      resolve(objectWrapper);
    });
  };

  const btn = document.createElement('button');
  btn.id = 'php-proxy-hunter-grab-proxy';
  btn.setAttribute('style', 'position: fixed; bottom: 50%; right: 2em; transform: translateY(50%);');
  btn.innerText = 'PARSE PROXIES';
  btn.classList.add('btn', 'button', 'btn-primary');
  btn.onclick = () => {
    parse().then((result) => {
      const html = result.map((obj) => JSON.stringify(obj)) + '<br/>';
      window.open(URL.createObjectURL(new Blob([html], { type: 'text/html' })), 'width=800,height=600');
    });
  };
  document.body.appendChild(btn);
})();
