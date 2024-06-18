// ==UserScript==
// @name         universal proxy parser
// @namespace    dimaslanjaka:universal-parser-proxy
// @version      1.0
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
// @match        *://spys.one/*/
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
// @require      https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js
// @downloadURL https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/universal.user.js
// @updateURL   https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/universal.user.js
// ==/UserScript==

(function () {
  "use strict";

  const addProxyFun = (dataToSend) => {
    if (!dataToSend) return;
    if (typeof dataToSend != "string") dataToSend = JSON.stringify(dataToSend);
    const services = [
      "https://sh.webmanajemen.com/proxyAdd.php",
      "https://sh.webmanajemen.com/proxyCheckerParallel.php"
    ];
    /**
     * fetch callback
     * @param {Promise<any>} obj
     * @returns {Promise<any>}
     */
    const cb = (obj) => {
      return obj
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
    services.forEach((url) => {
      cb(
        fetch(url, {
          signal: AbortSignal.timeout(5000),
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `proxies=${encodeURIComponent(dataToSend)}`
        })
      ).then(() => {
        cb(
          fetch(url, {
            signal: AbortSignal.timeout(5000),
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `proxy=${encodeURIComponent(dataToSend)}`
          })
        );
      });
    });
  };

  const parse_proxy_db_net = () => {
    return new Promise((resolve) => {
      const regex = /^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})$/;
      const result = [];
      const a = Array.from(document.getElementsByClassName("spy14"));
      for (var i = 0; i < a.length; i++) {
        if (a[i].innerText.includes(":")) {
          result.push(a[i].innerText);
        }
      }

      const tables = Array.from(document.querySelectorAll("table"));
      for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        const tr = Array.from(table.querySelectorAll("tr"));
        for (let ii = 0; ii < tr.length; ii++) {
          const td = Array.from(tr[ii].querySelectorAll("td"));
          if (td[0] && regex.test(td[0].innerText)) {
            result.push(td[0].innerText);
          }
        }
      }

      resolve(result);
    });
  };

  /**
   * parse hidemy proxy
   * @returns {Promise<any[]>}
   */
  const parse_hideme_jquery = () => {
    return new Promise((resolve) => {
      const result = [];
      // eslint-disable-next-line no-undef
      $(".table_block>table>tbody>tr").each(function (i, e) {
        // eslint-disable-next-line no-undef
        var tr = $(e);
        var tdList = tr.children("td");
        var host = tdList.get(0).innerText;
        var port = tdList.get(1).innerText;
        result.push(host + ":" + port);
      });

      resolve(result);
    });
  };

  /**
   * @returns {Promise<any[]>}
   */
  const parse_prem_proxy = () => {
    return new Promise((resolve) => {
      // Select all table elements on the page
      const tables = Array.from(document.querySelectorAll("table"));
      const ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
      const objectWrapper = [];

      // Loop through each table element using a for loop
      for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        const rows = Array.from(table.querySelectorAll("tr"));

        for (let j = 0; j < rows.length; j++) {
          const row = rows[j];
          const td = Array.from(row.querySelectorAll("td"));
          const texts = td.map((el) => el.innerText).filter((str) => typeof str == "string" && str.trim().length > 0);
          if (ipOnly.test(texts.join(" "))) {
            // console.log(texts);
            objectWrapper.push({
              raw: texts[0],
              ip: texts[0].split(":")[0],
              port: texts[0].split(":")[1],
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
   * @returns {Promise<any[]>}
   */
  const parse_proxylistplus = () => {
    return new Promise((resolve) => {
      // Select all table elements on the page
      const tables = Array.from(document.querySelectorAll("table"));
      const ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
      const objectWrapper = [];

      // Loop through each table element using a for loop
      for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        const rows = Array.from(table.querySelectorAll("tr"));

        for (let j = 0; j < rows.length; j++) {
          const row = rows[j];
          const td = Array.from(row.querySelectorAll("td"));
          const texts = td.map((el) => el.innerText).filter((str) => typeof str == "string" && str.trim().length > 0);
          if (ipOnly.test(texts.join(" "))) {
            const item = {
              raw: texts[0] + ":" + texts[1],
              ip: texts[0],
              port: texts[1],
              type: texts[2],
              country: texts[3],
              anonymity: texts[4],
              https: texts[5]
            };
            // console.log(item);
            objectWrapper.push(item);
          }
        }
      }

      resolve(objectWrapper);
    });
  };

  // parse_proxylistplus().then(console.log);

  /**
   * @returns {Promise<any[]>}
   */
  const parse_second_and_third_row = () => {
    return new Promise((resolve) => {
      // Select all table elements on the page
      const tables = Array.from(document.querySelectorAll("table"));
      const ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
      const objectWrapper = [];

      // Loop through each table element using a for loop
      for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        const rows = Array.from(table.querySelectorAll("tr"));

        for (let j = 0; j < rows.length; j++) {
          const row = rows[j];
          const td = Array.from(row.querySelectorAll("td"));
          const texts = td.map((el) => el.innerText).filter((str) => typeof str == "string" && str.trim().length > 0);
          if (ipOnly.test(texts.join(" "))) {
            // console.log(texts);
            objectWrapper.push({
              raw: texts[1] + ":" + texts[2],
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
   * @returns {Promise<any[]>}
   */
  const parse_first_and_second_row = () => {
    return new Promise((resolve) => {
      // Select all table elements on the page
      const tables = Array.from(document.querySelectorAll("table"));
      const ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
      const objectWrapper = [];

      // Loop through each table element using a for loop
      for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        const rows = Array.from(table.querySelectorAll("tr"));

        for (let j = 0; j < rows.length; j++) {
          const row = rows[j];
          const buildObject = {
            proxy: null,
            code: null,
            anonymity: null,
            ssl: null,
            google: null,
            alert: null,
            type: "http",
            test: null
          };
          const td = row.querySelectorAll("td");
          const proxy = td[0];
          const port = td[1];
          const countryCode = td[2];
          const anonymity = td[4];
          const google = td[5];
          const ssl = td[6];
          if (proxy && ssl && ipOnly.test(proxy.innerText)) {
            // console.log(proxy.innerText, port.innerText, countryCode.innerText, anonymity.innerText, google.innerText, ssl.innerText);
            buildObject.proxy = `${proxy.innerText.trim()}:${port.innerText.trim()}`;
            buildObject.google = /^yes/.test(google.innerText.trim()) ? true : false;
            buildObject.ssl = /^yes/.test(ssl.innerText.trim()) ? true : false;
            buildObject.code = countryCode.innerText.trim();
            switch (anonymity.innerText.trim()) {
              case "elite proxy":
                buildObject.anonymity = "H";
                break;
              case "anonymous":
                buildObject.anonymity = "A";
                break;

              default:
                buildObject.anonymity = "N";
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
   * parse IP:PORT from first row
   * @returns {Promise<any[]>}
   */
  const parse_first_row_ip_port = () => {
    return new Promise((resolve) => {
      const regex = /^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})$/;
      const result = [];
      const a = Array.from(document.getElementsByClassName("spy14"));
      for (var i = 0; i < a.length; i++) {
        if (a[i].innerText.includes(":")) {
          result.push(a[i].innerText);
        }
      }

      const tables = Array.from(document.querySelectorAll("table"));
      for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        const tr = Array.from(table.querySelectorAll("tr"));
        for (let ii = 0; ii < tr.length; ii++) {
          const td = Array.from(tr[ii].querySelectorAll("td"));
          if (td.length > 0 && regex.test(td[0].innerText)) {
            result.push(td[0].innerText);
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
  const extractIpPortPairs = (input) => {
    // Regular expression to match IP:PORT
    const regex = /(?:[0-9]{1,3}\.){3}[0-9]{1,3}:[0-9]{1,5}/g;
    return input.match(regex) || [];
  };

  const extractIpPortFromBody = () => {
    const area = document.querySelectorAll("textarea,td");
    let currentHtml = document.body.innerHTML;
    area.forEach((el) => {
      currentHtml += "\n" + el.value + "\n";
    });
    return Promise.resolve(extractIpPortPairs(currentHtml));
  };

  const parse_all = () => {
    return new Promise((resolve) => {
      const all = [
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
        .then((results) => {
          const flat = results.flat().filter((item) => {
            if (!item) return false;
            const str = typeof item == "string" ? item : JSON.stringify(item);
            const regex = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/gm;
            return regex.test(str);
          });
          resolve(flat.map((obj) => JSON.stringify(obj, null, 2)).join("\n"));
        })
        .catch((error) => {
          console.error(error);
          resolve([]);
        });
    });
  };

  /**
   * Monitors changes to the body's HTML content and performs actions when changes are detected.
   */
  const monitorBodyChanges = () => {
    let lastHtml = "";

    setInterval(() => {
      const currentHtml = document.body.innerHTML;
      if (currentHtml !== lastHtml) {
        lastHtml = currentHtml;
        parse_all().then(addProxyFun);
      }
    }, 3000); // Check every 3 seconds
  };

  setTimeout(monitorBodyChanges, 3000);

  const btn = document.createElement("button");
  btn.id = "php-proxy-hunter-grab-proxy";
  btn.setAttribute(
    "style",
    "position: fixed; top: 50%; left: 0; transform: translateY(-50%); opacity: 0.6; margin-left: 1.2em;"
  );
  btn.innerText = "PARSE PROXIES";
  btn.classList.add("btn", "button", "btn-primary");
  btn.onclick = () => {
    parse_all()
      .then((result) => {
        addProxyFun(result);
        const htmlContent = `
<!DOCTYPE html>
<html>
<head>
  <title>JSON Data</title>
</head>
<body>
  <pre>${result}</pre>
</body>
</html>`;

        window.open(URL.createObjectURL(new Blob([htmlContent], { type: "text/html" })), "width=800,height=600");
      })
      .catch((error) => {
        console.error(error);
      });
  };
  document.body.appendChild(btn);
})();
