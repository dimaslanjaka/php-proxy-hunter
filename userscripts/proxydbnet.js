// ==UserScript==
// @name         proxydb.net proxy parser
// @namespace    dimaslanjaka:proxydb-parser-proxy
// @version      1.0
// @description  parse proxy from site page
// @author       dimaslanjaka
// @match        https://proxydb.net/*
// @require      https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js
// @downloadURL https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/proxydbnet.js
// @updateURL   https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/proxydbnet.js
// ==/UserScript==

(function () {
  "use strict";
  const addProxyFun = (dataToSend) => {
    const url = "https://sh.webmanajemen.com/proxyAdd.php";
    fetch(url, {
      signal: AbortSignal.timeout(5000),
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `proxies=${encodeURIComponent(dataToSend)}`,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Network response was not ok");
        }
        return response.text();
      })
      .then((data) => {
        console.log(data);
      })
      .catch((error) => {
        console.log(
          "There was a problem with your fetch operation: " + error.message,
        );
      });
  };

  const regex = /^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})$/;

  const parse = () => {
    let resultText = "";
    const a = Array.from(document.getElementsByClassName("spy14"));
    for (var i = 0; i < a.length; i++) {
      if (a[i].innerText.includes(":")) {
        resultText += a[i].innerText + "<br>";
      }
    }

    const tables = Array.from(document.querySelectorAll("table"));
    for (let i = 0; i < tables.length; i++) {
      const table = tables[i];
      const tr = Array.from(table.querySelectorAll("tr"));
      for (let ii = 0; ii < tr.length; ii++) {
        const td = Array.from(tr[ii].querySelectorAll("td"));
        if (td[0] && regex.test(td[0].innerText)) {
          resultText += td[0].innerText + "<br>";
        }
      }
    }
    return resultText;
  };

  document.addEventListener("DOMContentLoaded", function (event) {
    setTimeout(() => {
      let proxies_str = parse();
      while (!proxies_str || proxies_str.trim().length == 0) {
        proxies_str = parse();
      }
      addProxyFun(proxies_str);
    }, 10000);
  });

  const btn = document.createElement("button");
  btn.id = "php-proxy-hunter-grab-proxy";
  btn.setAttribute("style", "position: fixed;bottom: 2em;right: 2em;");
  btn.innerText = "PARSE PROXIES";
  btn.classList.add("btn", "button", "btn-primary");
  btn.onclick = () => {
    window.open(
      URL.createObjectURL(new Blob([parse()], { type: "text/html" })),
      "width=800,height=600",
    );
  };
  document.body.appendChild(btn);
})();
