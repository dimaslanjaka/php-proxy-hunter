// ==UserScript==
// @name         premproxy proxy parser
// @namespace    dimaslanjaka:premproxy-parser-proxy
// @version      1.3
// @description  parse proxy from site page
// @author       dimaslanjaka
// @supportURL   https://github.com/dimaslanjaka/php-proxy-hunter/issues
// @homepageURL         https://dimaslanjaka.github.io/
// @contributionURL     https://github.com/dimaslanjaka/php-proxy-hunter
// @license             MIT
// @match        *://premproxy.com/*
// @noframes
// @run-at document-end
// @downloadURL https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/premproxy.user.js
// @updateURL   https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/premproxy.meta.js
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
          const td = Array.from(row.querySelectorAll('td'));
          const texts = td.map((el) => el.innerText).filter((str) => typeof str == 'string' && str.trim().length > 0);
          if (ipOnly.test(texts.join(' '))) {
            // console.log(texts);
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
