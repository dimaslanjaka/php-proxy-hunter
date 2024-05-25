/*
  ----------------------------------------------------------------------------
  LICENSE
  ----------------------------------------------------------------------------
  This file is part of Proxy Checker.

  Proxy Checker is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Proxy Checker is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with Proxy Checker.  If not, see <https://www.gnu.org/licenses/>.

  ----------------------------------------------------------------------------
  Copyright (c) 2024 Dimas lanjaka
  ----------------------------------------------------------------------------
  This project is licensed under the GNU General Public License v3.0
  For full license details, please visit: https://www.gnu.org/licenses/gpl-3.0.html

  If you have any inquiries regarding the license or permissions, please contact:

  Name: Dimas Lanjaka
  Website: https://www.webmanajemen.com
  Email: dimaslanjaka@gmail.com
*/

/**
 * @type {Record<string, any>|undefined}
 */
let user_info;

function noop() {
  //
}

async function main() {
  user_info = await userInfo();
  if (!user_info) {
    console.log("user null");
    // await main();
    location.reload();
    return;
  }

  document.getElementById("recheck").addEventListener("click", (e) => {
    e.preventDefault();
    showSnackbar("proxy checking start...");
    doCheck();
  });

  document.getElementById("filter-ports").addEventListener("click", (e) => {
    e.preventDefault();
    showSnackbar("filter open ports start...");
    fetch("./filterPortsBackground.php", { signal: AbortSignal.timeout(5000) }).catch((e) => showSnackbar(e.message));
  });

  // noinspection ES6MissingAwait
  checkerStatus();
  let interval_check = setInterval(() => {
    checkerStatus();
  }, 3000);

  const autoCheck = document.getElementById("autoCheckProxy");
  if (["dev.webmanajemen.com", "localhost", "127.0.0.1"].some((str) => new RegExp(str).test(location.host))) {
    autoCheck.addEventListener("change", (_e) => {
      clearInterval(interval_check);
      if (autoCheck.checked) {
        const callback = () =>
          checkerStatus().then((result) => {
            if (!result) doCheck();
          });
        callback().then(() => {
          interval_check = setInterval(callback, 3000);
        });
      } else {
        interval_check = setInterval(() => {
          checkerStatus();
        }, 3000);
      }
    });
  } else {
    document.getElementById("autoCheckProxy-wrapper").remove();
  }

  // noinspection ES6MissingAwait
  checkerOutput();
  setInterval(() => {
    checkerOutput();
  }, 3000);

  // noinspection ES6MissingAwait
  fetchWorkingProxies();
  setInterval(() => {
    fetchWorkingProxies();
  }, 5000);
}

/**
 * Performs a check using the proxyCheckerBackground.php endpoint.
 * @returns {Promise<void>} A promise that resolves when the check is completed.
 */
async function doCheck() {
  try {
    if (user_info) {
      await fetchWorkingProxies().catch(noop);
      await fetch("./proxyCheckerBackground.php?uid=" + user_info.user_id, {
        signal: AbortSignal.timeout(5000)
      }).catch(noop);
      await checkerStatus().catch(noop);
      await fetchWorkingProxies().catch(noop);
    }
  } catch (e) {
    showSnackbar(e.message);
  }
}

let prevOutput = "";

/**
 * get result of proxy checker
 */
async function checkerOutput() {
  const info = await fetch("./embed.php?file=proxyChecker.txt", {
    signal: AbortSignal.timeout(5000),
    mode: "cors"
  })
    .then((res) => res.text())
    .catch(noop);
  // skip update UI when output when remains same
  if (prevOutput === info) return;
  if (typeof info !== "string") return;
  if (info.trim().length === 0) return;
  prevOutput = info || "";
  const filter = (info || "")
    .split(/\r?\n/)
    .map((str) => {
      str = str.replace(/port closed/, '<span class="text-red-400">port closed</span>');
      str = str.replace(/not working/, '<span class="text-red-600">not working</span>');
      str = str.replace(/dead/, '<span class="text-red-600">dead</span>');
      str = str.replace(/working.*/, (whole) => {
        if (whole.includes("-1")) return `<span class="text-orange-400">${whole}</span>`;
        return `<span class="text-green-400">${whole}</span>`;
      });
      return str;
    })
    .join("<br/>");
  const checkerResult = document.getElementById("cpresult");
  checkerResult.innerHTML = filter;
  // Check if content height exceeds div height
  // Only scroll when checker status is running
  if (checkerResult.scrollHeight > checkerResult.clientHeight && checker_status) {
    // Scroll the div to the bottom
    checkerResult.scrollTop = checkerResult.scrollHeight - checkerResult.clientHeight;
  }

  const wrapper = document.querySelector("#nav-info");
  /**
   * @type {Record<string, any>}
   */
  const statusJson = await fetch("./status.json", {
    signal: AbortSignal.timeout(5000),
    mode: "cors",
    cache: "no-cache"
  })
    .then((res) => res.json())
    .catch(() => {
      return {};
    });
  if (statusJson.untested && statusJson.untested > 0) {
    wrapper.querySelector("#untested").innerText = parseInt(statusJson.untested).toLocaleString();
  }
  if (statusJson.all && statusJson.all > 0) {
    wrapper.querySelector("#all-total").innerText = parseInt(statusJson.all).toLocaleString();
  }
  if (statusJson.dead && statusJson.dead > 0) {
    wrapper.querySelector("#dead").innerText = parseInt(statusJson.dead).toLocaleString();
  }
  if (statusJson.working && statusJson.working > 0) {
    wrapper.querySelector("#working").innerText = parseInt(statusJson.working).toLocaleString();
  }
}

userInfo()
  .then((_) => {})
  .catch(() => {})
  .finally(() => {
    printBasicCurlCommand("curl-command");
    listenCurlCommandBuilder();
  });

function printBasicCurlCommand(preElementId) {
  console.log("print");
  // Get the current URL
  const currentUrl = window.location.href;

  // Extract protocol and domain
  const url = new URL(currentUrl);
  const protocol = url.protocol;
  const domain = url.hostname;

  // Define the endpoint and proxy
  const endpoint = "/proxyCheckerParallel.php";
  const proxy = "72.10.160.171:24049";

  // Create the curl command
  const curlCommand = `curl -X POST ${protocol}//${domain}${endpoint}\n     -d "proxy=${proxy}"`;

  console.log(curlCommand);

  // Find the <pre> element by its ID
  const preElement = document.getElementById(preElementId);

  // Check if the element exists
  if (preElement) {
    // Set the text content of the <pre> element
    preElement.textContent = curlCommand;
  } else {
    console.error(`Element with id "${preElementId}" not found.`);
  }
}

function listenCurlCommandBuilder() {
  document.getElementById("proxyForm").addEventListener("submit", function (event) {
    event.preventDefault(); // Prevent the form from submitting

    const target = document.getElementById("cpresult");
    const offset = 100; // Adjust this value to change the offset

    // Scroll to the element
    target.scrollIntoView({ behavior: "smooth" });

    // Adjust the scroll position
    setTimeout(() => {
      window.scrollBy({
        top: -offset, // Move up by the offset
        left: 0,
        behavior: "smooth"
      });
    }, 1000);

    // Get the form data
    const formData = new FormData(this);

    // Construct the fetch options
    const options = {
      method: "POST",
      body: formData
    };

    // Send the POST request
    fetch("proxyCheckerParallel.php", options)
      .then((response) => {
        if (!response.ok) {
          throw new Error("Network response was not ok");
        }
        return response.text();
      })
      .then((data) => {
        // Display the response in the result div
        document.getElementById("result").innerHTML =
          `<p class="text-green-600 font-semibold">POST request successful!</p>
          <pre class="mt-2 bg-gray-100 p-2 rounded text-black">${data}</pre>`;
      })
      .catch((error) => {
        console.error("Error:", error);
        document.getElementById("result").innerHTML =
          `<p class="text-red-600 font-semibold">Error: ${error.message}</p>`;
      });
  });

  document.querySelector("textarea#proxy").addEventListener("change", function (e) {
    const value = e.target.value;
    const encoded = encodeURI(value);
    const resultCurl = document.getElementById("result-curl");
    const origin = window.location.origin;

    // Add the pre element with the copy button
    resultCurl.innerHTML = `
    <div class="relative">
      <pre class="dark:bg-gray-800 dark:text-white whitespace-pre-wrap break-words p-4"><code class="dark:text-white">curl -X POST ${origin}/proxyCheckerParallel.php -d "proxy=${encoded}"</code></pre>
      <button id="copyButton" class="absolute top-2 right-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold py-1 px-2 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">Copy</button>
    </div>
  `;

    // Add event listener to the copy button
    document.getElementById("copyButton").addEventListener("click", function () {
      const codeElement = resultCurl.querySelector("code");
      const textToCopy = codeElement.innerText;

      navigator.clipboard.writeText(textToCopy).then(
        function () {
          console.log("Text copied to clipboard");
          // Optionally, provide feedback to the user that the text was copied
          alert("Copied to clipboard");
        },
        function (err) {
          console.error("Could not copy text: ", err);
        }
      );
    });
  });
}

async function userInfo() {
  try {
    let cookie = getCookie("user_config");
    if (!cookie) {
      await fetch("./info.php", {
        signal: AbortSignal.timeout(5000),
        mode: "cors"
      });
      cookie = getCookie("user_config");
    }
    return JSON.parse(atob(decodeURIComponent(cookie)));
  } catch (_) {
    //
  }
}

function getCookie(name) {
  const cookieString = document.cookie;
  const cookies = cookieString.split("; ");

  for (let i = 0; i < cookies.length; i++) {
    const cookie = cookies[i].split("=");
    if (cookie[0] === name) {
      return cookie[1];
    }
  }

  return null;
}

let checker_status;

/**
 * check checker status
 * @returns true=running false=idle
 */
async function checkerStatus() {
  const status = document.querySelector("span#status");
  const cek = document.getElementById("recheck");
  return await fetch("./embed.php?file=status.txt", {
    signal: AbortSignal.timeout(5000),
    mode: "cors"
  })
    .then((res) => res.text())
    .then((data) => {
      if (!data.trim().includes("idle")) {
        // another php still processing
        if (!cek.classList.contains("disabled")) cek.classList.add("disabled");
        status.innerHTML = data.trim().toUpperCase();
        status.setAttribute(
          "class",
          "inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20"
        );
        checker_status = true;
        return true;
      } else {
        checker_status = false;
        cek.classList.remove("disabled");
        status.setAttribute(
          "class",
          "inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10"
        );
        status.innerHTML = "IDLE";
      }
      return false;
    })
    .catch(() => {
      return false;
    });
}

function fetchWorkingData() {
  fetch("./proxyWorkingBackground.php", {
    signal: AbortSignal.timeout(5000),
    mode: "cors"
  }).catch(() => {
    // Handle errors if needed
  });
}

const fetchWorkingEveryMinutes = 1;

function setLastExecutionTime() {
  const now = new Date();
  now.setTime(now.getTime() + fetchWorkingEveryMinutes * 60 * 1000); // Set expiration time 5 minutes from now
  document.cookie = "lastExecutionTime=" + now.toUTCString() + "; path=" + location.pathname;
}

function getLastExecutionTime() {
  const name = "lastExecutionTime=";
  const decodedCookie = decodeURIComponent(document.cookie);
  const cookieArray = decodedCookie.split(";");
  for (let i = 0; i < cookieArray.length; i++) {
    let cookie = cookieArray[i];
    while (cookie.charAt(0) === " ") {
      cookie = cookie.substring(1);
    }
    if (cookie.indexOf(name) === 0) {
      return new Date(cookie.substring(name.length, cookie.length));
    }
  }
  return null;
}

function updateWorkingProxies() {
  const lastExecutionTime = getLastExecutionTime();
  const currentTime = new Date();
  if (
    !lastExecutionTime ||
    currentTime.getTime() - lastExecutionTime.getTime() >= fetchWorkingEveryMinutes * 60 * 1000
  ) {
    fetchWorkingData();
    setLastExecutionTime();
  }
}

let workingProxiesTxt;

async function fetchWorkingProxies() {
  // fetch update in background
  updateWorkingProxies();
  let testWorkingProxiesTxt = await fetch("./embed.php?file=working.txt", {
    signal: AbortSignal.timeout(5000),
    mode: "cors"
  })
    .then((res) => res.text())
    .catch(() => "");
  // skip update UI when response empty
  if (testWorkingProxiesTxt.trim().length === 0) return;
  if (!workingProxiesTxt || workingProxiesTxt !== testWorkingProxiesTxt) {
    workingProxiesTxt = testWorkingProxiesTxt;
    // const proxies = sortLinesByDate(workingProxiesTxt);
    const proxies = workingProxiesTxt.split(/\r?\n/).filter((line) => line.trim() !== "");
    const tbody = document.getElementById("wproxy");
    tbody.innerHTML = "";
    proxies.forEach((str) => {
      const tr = document.createElement("tr");
      const split = str.split("|");
      split.forEach((info, i) => {
        const td = document.createElement("td");
        td.setAttribute(
          "class",
          "border-b border-slate-100 dark:border-slate-700 p-4 text-slate-500 dark:text-slate-400"
        );
        td.innerText = info;
        if (i === 0 || i > 13 || (i >= 7 && i <= 9)) {
          // td.classList.add("w-4/12");
          if (td.innerText !== "-")
            td.innerHTML += `<button class="rounded-full ml-2 pcopy" data="${info}"><i class="fa-duotone fa-copy"></i></button>`;
        } else if (i === 2 && info.length > 6) {
          // last check date
          td.innerText = timeAgo(info);
        } else {
          td.classList.add("text-center");
        }

        if (i === 7 || i === 6 || (i > 13 && i <= 18)) {
          if (info.trim() === "-") {
            // console.log(split[0], "missing geo location");
            add_ajax_schedule(
              "./geoIpBackground.php?proxy=" + encodeURIComponent(split[0]) + "&uid=" + user_info.user_id
            );
            run_ajax_schedule();
          }
        }

        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    document.querySelectorAll(".pcopy").forEach((el) => {
      if (el.hasAttribute("aria-copy")) return;
      el.addEventListener("click", () => {
        copyToClipboard(el.getAttribute("data").trim());
        showSnackbar("proxy copied");
      });
      el.setAttribute("aria-copy", el.getAttribute("data"));
    });
  }
}

/**
 * list url to be executed
 * @type {string[]}
 */
const ajax_url_schedule = [];

/**
 * ajax schedule runner indicator
 * @type {boolean}
 */
let ajax_schedule_running = false;

/**
 * Runs the AJAX schedule.
 */
function run_ajax_schedule() {
  if (!ajax_schedule_running) {
    ajax_schedule_running = true;
    const url = ajax_url_schedule.shift();
    fetch(url, {
      signal: AbortSignal.timeout(5000)
    })
      .catch(() => {
        // re-push the url when error
        add_ajax_schedule(url);
      })
      .finally(() => {
        ajax_schedule_running = false;
        // repeat
        if (ajax_url_schedule.length > 0) run_ajax_schedule();
      });
  }
}

/**
 * Adds a URL to the AJAX schedule if it's not already present.
 * @param {string} url - The URL to add.
 */
function add_ajax_schedule(url) {
  if (!ajax_url_schedule.includes(url)) {
    ajax_url_schedule.push(url);
  }
}

/**
 * Converts a given date string to a human-readable "time ago" format.
 * @param {string} dateString - The date string to be converted.
 * @returns {string} The time ago format of the provided date string.
 */
function timeAgo(dateString) {
  // Convert the provided date string to a Date object
  const date = new Date(dateString);

  // return invalid date to original string
  if (isNaN(date.getTime())) return dateString;

  // Get the current time
  const now = new Date();

  // Calculate the time difference in milliseconds
  const difference = now - date;

  // Convert milliseconds to seconds, minutes, hours, and days
  const seconds = Math.floor(difference / 1000);
  const minutes = Math.floor(seconds / 60);
  const hours = Math.floor(minutes / 60);
  const days = Math.floor(hours / 24);

  // Calculate remaining hours, minutes, and seconds
  const remainingHours = hours % 24;
  const remainingMinutes = minutes % 60;
  const remainingSeconds = seconds % 60;

  // Construct the ago time string
  let agoTime = "";
  if (days > 0) agoTime += days + " day" + (days === 1 ? "" : "s") + " ";
  if (remainingHours > 0) agoTime += remainingHours + " hour" + (remainingHours === 1 ? "" : "s") + " ";
  if (remainingMinutes > 0) agoTime += remainingMinutes + " minute" + (remainingMinutes === 1 ? "" : "s") + " ";
  if (remainingSeconds > 0) agoTime += remainingSeconds + " second" + (remainingSeconds === 1 ? "" : "s") + " ";

  // Append "ago" to the ago time string
  agoTime += "ago";

  return agoTime;
}

/**
 * Displays a snackbar message for a specified duration.
 * @param {...string|Error} messages - The messages to be displayed, which can also be an Error object.
 */
function showSnackbar(...messages) {
  // Get the snackbar element
  const snackbar = document.getElementById("snackbar");

  // Combine all messages into one string
  // Set the message
  snackbar.textContent = messages
    .map((msg) => {
      if (msg instanceof Error) {
        // If message is an Error object, extract the error message
        return `Error: ${msg.message}`;
      } else if (typeof msg !== "string") {
        // If message is not a string, stringify it
        return JSON.stringify(msg);
      } else {
        return msg;
      }
    })
    .join(" ");

  // Add the "show" class to DIV
  snackbar.classList.add("show");

  // Hide the snackbar after 3 seconds
  setTimeout(function () {
    snackbar.classList.remove("show");
  }, 3000);
}

/**
 * Copies a string to the clipboard. Must be called from within an
 * event handler such as click. May return false if it failed, but
 * this is not always possible. Browser support for Chrome 43+,
 * Firefox 42+, Safari 10+, Edge and Internet Explorer 10+.
 * Internet Explorer: The clipboard feature may be disabled by
 * an administrator. By default, a prompt is shown the first
 * time the clipboard is used (per session).
 * @param {string} text - The text to be copied to the clipboard.
 * @returns {boolean} - Returns true if the operation succeeds, otherwise returns false.
 */
function copyToClipboard(text) {
  try {
    if (navigator.clipboard) {
      return navigator.clipboard
        .writeText(text)
        .then(() => true)
        .catch((err) => {
          showSnackbar("Error copying to clipboard:", err);
          return false;
        });
    } else if (window.clipboardData && window.clipboardData.setData) {
      // Internet Explorer-specific code path to prevent textarea being shown while dialog is visible.
      return window.clipboardData.setData("Text", text);
    } else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
      const textarea = document.createElement("textarea");
      textarea.textContent = text;
      textarea.style.position = "fixed"; // Prevent scrolling to bottom of page in Microsoft Edge.
      document.body.appendChild(textarea);
      textarea.select();
      try {
        return document.execCommand("copy"); // Security exception may be thrown by some browsers.
      } catch (ex) {
        showSnackbar("Copy to clipboard failed.", ex);
        return false;
      } finally {
        document.body.removeChild(textarea);
      }
    } else {
      showSnackbar("Copying to clipboard not supported.");
      return false;
    }
  } catch (err) {
    showSnackbar("Error copying to clipboard:", err);
    return false;
  }
}

/**
 * @returns {Promise<T|{}>}
 */
async function getUserInfo() {
  return await fetch("./info.php")
    .then((r) => r.json())
    .catch(() => {
      return {};
    });
}

async function init_config_editor() {
  /**
   * @type {Record<string, any>}
   */
  const info = await getUserInfo();
  const endpoint = document.querySelector("input[name=endpoint]");
  const headers = document.querySelector("textarea[name=headers]");
  const checkbox_http = document.querySelector("input[name=http]");
  const checkbox_socks4 = document.querySelector("input[name=socks4]");
  const checkbox_socks5 = document.querySelector("input[name=socks5]");
  if (Object.prototype.hasOwnProperty.call(info, "endpoint")) {
    endpoint.value = info.endpoint;
  }
  if (Object.prototype.hasOwnProperty.call(info, "headers")) {
    headers.value = info.headers.join("\n");
  }
  if (Object.prototype.hasOwnProperty.call(info, "type")) {
    info.type.split("|").map((protocol) => {
      document.querySelector(`input[name=${protocol}]`).checked = true;
    });
  }

  let sending_config, sending_proxies;

  const submit_config = (e) => {
    e.preventDefault();
    clearTimeout(sending_config); // Clear the previous timeout
    sending_config = setTimeout(modify_config, 1000); // Set a new timeout
  };

  document.getElementById("submit-config").addEventListener("click", submit_config);

  [endpoint, headers, checkbox_http, checkbox_socks4, checkbox_socks5].forEach((el) => {
    el.addEventListener("change", submit_config);
  });

  const submit_proxies = (e) => {
    e.preventDefault();
    clearTimeout(sending_proxies); // Clear the previous timeout
    sending_proxies = setTimeout(() => addProxy(document.getElementById("add_proxies").value), 1000); // Set a new timeout
  };

  document.getElementById("add_proxies").addEventListener("change", submit_proxies);
  document.getElementById("submit-new-proxies").addEventListener("click", submit_proxies);
}

async function addProxy(proxies) {
  const send = async function (dataToSend) {
    const url = `//${location.host}/proxyAdd.php`;

    try {
      const response = await fetch(url, {
        signal: AbortSignal.timeout(5000),
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded" // Sending form-urlencoded data
        },
        body: `proxies=${encodeURIComponent(dataToSend)}` // Encode the string for safe transmission
      });
      if (!response.ok) {
        showSnackbar("Network response was not ok");
      } else {
        const data = await response.text();
        showSnackbar(data);
      }
    } catch (error) {
      showSnackbar("There was a problem with your fetch operation: " + error.message);
    }
  };
  const ipPortArray = proxies
    .trim()
    .split(/\r?\n/)
    .filter((text) => text.match(/(?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:(?!0)\d{2,5}/gim));

  const chunkSize = 1000;
  const chunkedArrays = [];
  for (let i = 0; i < ipPortArray.length; i += chunkSize) {
    chunkedArrays.push(ipPortArray.slice(i, i + chunkSize));
  }
  for (const arr of chunkedArrays) {
    await send(arr.join("\n"));
  }
}

function modify_config() {
  let type = document.querySelector("[name=http]").checked ? "http" : "";
  type += document.querySelector("[name=socks5]").checked ? "|" + "socks5" : "";
  type += document.querySelector("[name=socks4]").checked ? "|" + "socks4" : "";
  fetch("./info.php", {
    signal: AbortSignal.timeout(5000),
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      config: {
        headers: document.querySelector("[name=headers]").value.trim().split(/\r?\n/),
        endpoint: document.querySelector("[name=endpoint]").value.trim(),
        type: type.trim()
      }
    })
  })
    .then((response) => {
      if (!response.ok) {
        showSnackbar("Modify config error: Network response was not ok");
      }
      return response.json();
    })
    .then((_data) => {
      showSnackbar("Modify config success");
      console.log("response", _data);
    })
    .catch((error) => {
      showSnackbar("Modify config", error);
    });
}

(function () {
  main().then((_) => {});
  init_config_editor().then((_) => {});
})();
