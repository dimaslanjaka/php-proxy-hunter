// ==UserScript==
// @name         proxy-list.org proxy parser
// @namespace    dimaslanjaka:proxy-list-parser-proxy
// @version      1.0
// @description  parse proxy from site page
// @author       dimaslanjaka
// @supportURL   https://github.com/dimaslanjaka/php-proxy-hunter/issues
// @homepageURL         https://dimaslanjaka.github.io/
// @contributionURL     https://github.com/dimaslanjaka/php-proxy-hunter
// @license             MIT
// @match        *://proxy-list.org/*
// @downloadURL https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/proxy-list.user.js
// @updateURL   https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/proxy-list.meta.js
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

  const regex = /^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})$/;

  const parse = () => {
    const arr_result = [];
    const a = Array.from(document.getElementsByClassName('spy14'));
    for (let i = 0; i < a.length; i++) {
      if (a[i].innerText.includes(':')) {
        arr_result.push(a[i].innerText);
      }
    }

    const tables = Array.from(document.querySelectorAll('table'));
    for (let i = 0; i < tables.length; i++) {
      const table = tables[i];
      const tr = Array.from(table.querySelectorAll('tr'));
      for (let ii = 0; ii < tr.length; ii++) {
        const td = Array.from(tr[ii].querySelectorAll('td'));
        if (regex.test(td[0].innerText)) {
          arr_result.push(td[0].innerText);
        }
      }
    }

    const uls = Array.from(document.querySelectorAll('ul'));
    for (let i = 0; i < uls.length; i++) {
      const ul = uls[i];
      const es = ul.querySelector('li.proxy');
      if (es) arr_result.push(es.innerText);
    }

    const resultText = filterValidProxy(arr_result).join('<br/>');
    addProxyFun(resultText);
    return resultText;
  };

  setTimeout(parse, 3000);

  const btn = document.createElement('button');
  btn.id = 'php-proxy-hunter-grab-proxy';
  btn.setAttribute('style', 'position: fixed;bottom: 2em;right: 2em;');
  btn.innerText = 'PARSE PROXIES';
  btn.classList.add('btn', 'button', 'btn-primary');
  btn.onclick = () => {
    window.open(URL.createObjectURL(new Blob([parse()], { type: 'text/html' })), 'width=800,height=600');
  };
  document.body.appendChild(btn);
})();

function filterValidProxy(array) {
  const regexPattern = /^(?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:(?!0)\d{2,5}$/;
  return array.filter((item) => regexPattern.test(item));
}
