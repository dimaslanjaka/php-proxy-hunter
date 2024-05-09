// ==UserScript==
// @name         hidemy.io proxy parser
// @namespace    dimaslanjaka:hideme-parser-proxy
// @version      1.1
// @description  parse proxy from site page
// @author       dimaslanjaka
// @match        https://hidemy.name/*/proxy-list/*
// @match        https://hidemy.io/*/proxy-list/*
// @match        https://hidemyna.me/*/proxy-list/*
// @require      https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js
// @downloadURL https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/hidemy.js
// @updateURL   https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/hidemy.js
// ==/UserScript==

(function () {
  "use strict";

  const addProxyFun = (dataToSend) => {
    const url = "https://sh.webmanajemen.com/proxyAdd.php";
    fetch(url, {
      signal: AbortSignal.timeout(5000),
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `proxies=${encodeURIComponent(dataToSend)}`
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
        console.log("There was a problem with your fetch operation: (" + error.message + ")");
      });
  };

  // eslint-disable-next-line no-undef
  $(".table_block>table>tbody>tr").on("click", function () {
    // eslint-disable-next-line no-undef
    let tds = $(this).children("td");
    let ip = tds.eq(0).text();
    let port = tds.eq(1).text();
    let host = ip + ":" + port;
    // eslint-disable-next-line no-undef
    GM_setClipboard(host);
    // eslint-disable-next-line no-undef
    GM_notification({
      title: "Copied",
      text: host,
      timeout: 2000,
      silent: true
    });
  });

  const parseNow = () => {
    var resultText = "";
    // eslint-disable-next-line no-undef
    $(".table_block>table>tbody>tr").each(function (i, e) {
      // eslint-disable-next-line no-undef
      var tr = $(e);
      var tdList = tr.children("td");
      var host = tdList.get(0).innerText;
      var port = tdList.get(1).innerText;
      resultText += host + ":" + port + "<br>";
    });
    addProxyFun(resultText);
    return resultText;
  };

  const btn = document.createElement("button");
  btn.id = "php-proxy-hunter-grab-proxy";
  btn.setAttribute("style", "position: fixed;bottom: 2em;left: 2em;");
  btn.innerText = "PARSE PROXIES";
  btn.classList.add("btn", "button", "btn-primary");
  btn.onclick = () => {
    window.open(URL.createObjectURL(new Blob([parseNow()], { type: "text/html" })), "width=800,height=600");
  };
  document.body.appendChild(btn);
})();
