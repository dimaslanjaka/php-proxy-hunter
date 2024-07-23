// ==UserScript==
// @name         universal proxy parser
// @namespace    dimaslanjaka:universal-parser-proxy
// @version      1.2
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
// @require      https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js
// @require      https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js
// @downloadURL https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/universal.user.js
// @updateURL   https://raw.githack.com/dimaslanjaka/php-proxy-hunter/master/userscripts/universal.user.js
// ==/UserScript==

(function () {
  "use strict";

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
    const lines = input.split("\n");

    // Initialize an array to hold chunks of lines
    const chunks = [];

    // Loop through lines and group into chunks
    for (let i = 0; i < lines.length; i += linesPerChunk) {
      // Slice the array to get the chunk of lines
      const chunk = lines.slice(i, i + linesPerChunk);

      // Join the chunk back into a single string and push it to the array
      chunks.push(chunk.join("\n"));
    }

    return chunks;
  };

  /**
   * upload and check proxy
   * @param {string} dataToSend
   * @returns
   */
  const addProxyFun = (dataToSend) => {
    if (!dataToSend) return;
    if (typeof dataToSend != "string") dataToSend = JSON.stringify(dataToSend, null, 2);

    // Check if the data has already been sent by looking at local storage
    const hasDataBeenSent = (data) => {
      if (typeof data !== "string") data = md5(JSON.stringify(data));
      if (!isMD5Format(data)) data = md5(data);
      const sentData = localStorage.getItem("sentData");
      const result = sentData && sentData.includes(data);
      console.log(data, "is same", result);
      return result;
    };

    // Function to add data to local storage
    const markDataAsSent = (data) => {
      // skip null data
      if (!data) return;
      if (!hasDataBeenSent(data)) {
        if (typeof data !== "string") data = md5(JSON.stringify(data));
        if (!isMD5Format(data)) data = md5(data);
        try {
          let sentData = localStorage.getItem("sentData") || "";
          sentData += data + "\n"; // Append the entire data
          localStorage.setItem("sentData", sentData);
        } catch (_e) {
          console.log("RESET LOCAL STORAGE DATA");
          // reset local storage
          localStorage.setItem("sentData", data);
        }
      }
    };

    if (hasDataBeenSent(dataToSend)) return;

    const services = [
      "https://sh.webmanajemen.com/proxyAdd.php",
      "https://sh.webmanajemen.com/proxyCheckerParallel.php",
      "http://sh.webmanajemen.com:8880/proxy/check"
    ];

    /**
     * Function to perform fetch with delay
     * @param {string} url
     * @param {string} dataToSend
     */
    const fetchWithDelay = (url, dataToSend) => {
      return new Promise((resolve, reject) => {
        setTimeout(() => {
          fetch(url, {
            signal: AbortSignal.timeout(5000),
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded", "X-Greasemonkey-Script": "1" },
            body: dataToSend
          })
            .then((response) => {
              if (!response.ok) {
                // Log all response headers
                const headers = [];
                response.headers.forEach((value, name) => {
                  headers.push({ name, value });
                });
                return response.text().then((body) => {
                  return reject({
                    status: `${response.status} ${response.statusText}`,
                    message: `Network response to ${url} was not ok`,
                    headers,
                    body
                  });
                });
              }
              return response.text();
            })
            .then((data) => {
              console.log(data);
              resolve();
            })
            .catch((error) => {
              reject({
                message: `There was a problem with your fetch operation: (${error.message})`
              });
            });
        }, 1000); // 1 second delay
      });
    };

    services.forEach((url) => {
      const do_upload = (str_data) => {
        fetchWithDelay(url, `proxy=${encodeURIComponent(str_data)}`)
          .then(() => fetchWithDelay(url, `proxies=${encodeURIComponent(str_data)}`))
          .catch((error) => {
            console.error("Failed to fetch with delay:", error);
          });
      };
      const split_body = splitStringByLines(dataToSend, 100);
      if (!url.includes("proxyCheckerParallel")) {
        split_body.forEach(do_upload);
      } else {
        const item = split_body[Math.floor(Math.random() * split_body.length)];
        do_upload(item);
      }
      markDataAsSent(dataToSend);
    });
  };

  const parse_proxy_db_net = () => {
    return new Promise((resolve) => {
      const regex = /^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})$/;
      const result = [];
      const a = Array.from(document.getElementsByClassName("spy14"));
      for (var i = 0; i < a.length; i++) {
        if (a[i].innerText.includes(":")) {
          result.push({ raw: a[i].innerText });
        }
      }

      const tables = Array.from(document.querySelectorAll("table"));
      for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        const tr = Array.from(table.querySelectorAll("tr"));
        for (let ii = 0; ii < tr.length; ii++) {
          const td = Array.from(tr[ii].querySelectorAll("td"));
          if (td[0] && regex.test(td[0].innerText)) {
            result.push({ raw: td[0].innerText });
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
        result.push({ raw: host + ":" + port });
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
            raw: null,
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
            buildObject.raw = `${proxy.innerText.trim()}:${port.innerText.trim()}`;
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
          result.push({ raw: a[i].innerText });
        }
      }

      const tables = Array.from(document.querySelectorAll("table"));
      for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        const tr = Array.from(table.querySelectorAll("tr"));
        for (let ii = 0; ii < tr.length; ii++) {
          const td = Array.from(tr[ii].querySelectorAll("td"));
          if (td.length > 0 && regex.test(td[0].innerText)) {
            result.push({ raw: td[0].innerText });
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
    if (!input) return [];
    // Regular expression to match IP:PORT
    const regex = /(?:[0-9]{1,3}\.){3}[0-9]{1,3}:[0-9]{1,5}/g;
    return input.match(regex) || [];
  };

  const extractIpPortFromBody = () => {
    const result = [];
    const area = document.querySelectorAll("textarea,td");
    result.push(...extractIpPortPairs(document.body.innerHTML));
    area.forEach((el) => {
      result.push(...extractIpPortPairs(el.value));
    });
    const divList = document.querySelectorAll("div.list");
    divList.forEach((el) => {
      result.push(...extractIpPortPairs(el.innerHTML));
    });
    const unique = result.filter((str, index, self) => index === self.findIndex((t) => t === str));
    const map = unique.map((str) => {
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
   * @returns {Promise<string[]>}
   */
  const freeProxySale = () => {
    return new Promise((resolve) => {
      const result = [];
      const proxyTable = document.querySelectorAll(".proxy__table");
      proxyTable.forEach((wrapper) => {
        Array.from(wrapper.querySelectorAll("[class^=css-]")).forEach((el) => {
          const ips = findIPv4Addresses(el.textContent);
          if (ips.length > 0) {
            ips.forEach((ip) => {
              result.push({ raw: `${ip}:80` });
              result.push({ raw: `${ip}:443` });
              result.push({ raw: `${ip}:8080` });
              result.push({ raw: `${ip}:8000` });
            });
          }
        });
      });
      resolve(result);
    });
  };

  const parse_all = () => {
    return new Promise((resolve) => {
      /**
       * @type {Promise<{ raw: string }[]>[]}
       */
      const all = [
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
        .then((results) => {
          // flatting
          const flat = results.flat().filter((item) => {
            if (!item) return false;
            const str = typeof item == "string" ? item : JSON.stringify(item);
            const regex = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/gm;
            return regex.test(str);
          });
          // remove non IP:PORT
          const filterMap = flat.map((item) => {
            let valid = false;
            const regex_ip = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/gm;
            const regex_port = /(\d{1,5})/gm;
            const regex_proxy = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/gm;
            if (typeof item == "object") {
              if (item.raw) {
                valid = regex_proxy.test(item.raw);
              }
              if (!valid) {
                if (item.ip) {
                  if (regex_proxy.test(item.ip)) {
                    item.raw = item.ip;
                    item.ip = item.raw.split(":")[0];
                  }
                }
              }
              if (item.raw) {
                // fix IP:PORT
                const split = item.raw.split(":");
                let build_proxy = [];
                if (split.length > 1) {
                  split.forEach((str) => {
                    if (regex_ip.test(str)) {
                      build_proxy[0] = str;
                    } else if (regex_port.test(str)) {
                      build_proxy[1] = str;
                    }
                  });
                  if (regex_proxy.test(build_proxy.join(":"))) {
                    item.raw = build_proxy.join(":");
                  }
                }
              }
            }
            return item;
          });
          // unique
          const uniqueArray = filterMap.filter(
            (obj, index, self) => index === self.findIndex((t) => t.raw === obj.raw)
          );
          resolve(uniqueArray.map((obj) => JSON.stringify(obj, null, 2)).join("\n"));
        })
        .catch((error) => {
          console.error(error);
          resolve([]);
        });
    });
  };

  /**
   * Function to sanitize HTML by removing specified tags and the 'style' attribute.
   * @param {string} html - The HTML content to sanitize.
   * @returns {string} The sanitized HTML content.
   */
  const sanitizeHtml = (html) => {
    const doc = new DOMParser().parseFromString(html, "text/html");

    // Tags to remove
    const tagsToRemove = ["img", "script", "iframe", "link", "ins"];

    tagsToRemove.forEach((tagName) => {
      const tags = doc.getElementsByTagName(tagName);
      for (let i = tags.length - 1; i >= 0; i--) {
        tags[i].parentNode.removeChild(tags[i]);
      }
    });

    // Remove 'style' attribute from all tags
    const allTags = doc.getElementsByTagName("*");
    for (let i = 0; i < allTags.length; i++) {
      allTags[i].removeAttribute("style");
    }

    const filteredHtml = doc.documentElement.outerHTML;

    const doc2 = new DOMParser().parseFromString(filteredHtml, "text/html");
    const elements = [];
    doc2.querySelectorAll("textarea,table,.list").forEach((el) => {
      elements.push(el.outerHTML);
    });
    return elements.join("\n");
  };

  /**
   * Monitors changes to the body's HTML content and performs actions when changes are detected.
   */
  const monitorBodyChanges = () => {
    let lastHtml = "";

    setInterval(() => {
      const currentHtml = document.body.innerHTML;
      const sanitizedHtml = sanitizeHtml(currentHtml);
      if (sanitizedHtml !== lastHtml) {
        lastHtml = sanitizedHtml;
        console.log("body changed");
        parse_all().then(addProxyFun);
      }
    }, 3000); // Check every 3 seconds
  };

  setTimeout(monitorBodyChanges, 3000);

  const btn = document.createElement("button");
  btn.id = "php-proxy-hunter-grab-proxy";
  btn.setAttribute(
    "style",
    "position: fixed; top: 50%; left: 0; transform: translateY(-50%); opacity: 0.6; margin-left: 1.2em; color: white; background-color: black;"
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
