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

let user_info;

async function main() {
  user_info = await userInfo();
  if (!user_info) {
    console.log("user null");
    await main();
    location.reload();
    return;
  }

  document.getElementById("recheck").addEventListener("click", () => {
    showSnackbar("proxy checking start...");
    doCheck();
  });

  checkerStatus();
  let icheck = setInterval(() => {
    checkerStatus();
  }, 3000);

  const autoCheck = document.getElementById("autoCheckProxy");
  if (["dev.webmanajemen.com", "localhost", "127.0.0.1"].some((str) => new RegExp(str).test(location.host))) {
    autoCheck.addEventListener("change", (e) => {
      clearInterval(icheck);
      if (e.target.checked) {
        const callback = () =>
          checkerStatus().then((result) => {
            if (!result) doCheck();
          });
        callback().then(() => {
          icheck = setInterval(callback, 3000);
        });
      } else {
        icheck = setInterval(() => {
          checkerStatus();
        }, 3000);
      }
    });
  } else {
    autoCheck.parentElement.remove();
  }

  checkerOutput();
  setInterval(() => {
    checkerOutput();
  }, 3000);

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
      await fetchWorkingProxies().catch(() => {});
      await fetch("./proxyCheckerBackground.php?uid=" + user_info.user_id, {
        signal: AbortSignal.timeout(5000)
      }).catch(() => {});
      await checkerStatus().catch(() => {});
      await fetchWorkingProxies().catch(() => {});
    }
  } catch (error) {
    // Handle errors if needed
  }
}

let prevOutput = "";
/**
 * get result of proxy checker
 */
async function checkerOutput() {
  const info = await fetch("./proxyChecker.txt?v=" + new Date(), { signal: AbortSignal.timeout(5000) }).then((res) =>
    res.text()
  );
  // skip update UI when output when remains same
  if (prevOutput == info || info.trim().length == 0) return;
  prevOutput = info;
  const filter = info
    .split(/\r?\n/)
    .map((str) => {
      str = str.replace(/port closed/, '<span class="text-red-400">port closed</span>');
      str = str.replace(/not working/, '<span class="text-red-600">not working</span>');
      str = str.replace(/working type (\w+) latency (-?\d+) ms/, (whole, g1, g2) => {
        whole = whole.replace(g1, `<b>${g1}</b>`).replace(g2, `<b>${g2}</b>`);
        if (g2 != "-1") {
          return `<span class="text-green-400">${whole}</span>`;
        } else {
          return `<span class="text-orange-400">${whole}</span>`;
        }
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

  const wrapper = document.querySelector("#countProxy");
  const statusJson = await fetch("./status.json?v=" + new Date(), { signal: AbortSignal.timeout(5000) })
    .then((res) => res.json())
    .catch(() => {
      return {};
    });
  if (statusJson.untested)
    wrapper.querySelector("#untested").innerText = parseInt(statusJson.untested).toLocaleString();
  if (statusJson.dead) wrapper.querySelector("#dead").innerText = parseInt(statusJson.dead).toLocaleString();
}

fetch("./info.php", { signal: AbortSignal.timeout(5000) });

async function userInfo() {
  try {
    let cookie = getCookie("user_config");
    if (!cookie) {
      await fetch("./info.php", { signal: AbortSignal.timeout(5000) });
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
  return await fetch("./status.txt?v=" + new Date(), { signal: AbortSignal.timeout(5000) })
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

let workingProxiesTxt;
async function fetchWorkingProxies() {
  const date = new Date();
  // fetch update in background
  await fetch("./proxyWorkingBackground.php", { signal: AbortSignal.timeout(5000) }).catch(() => {
    //
  });
  let testWorkingProxiesTxt = await fetch("./working.txt?v=" + date, { signal: AbortSignal.timeout(5000) })
    .then((res) => res.text())
    .catch(() => "");
  // skip update UI when response empty
  if (testWorkingProxiesTxt.trim().length == 0) return;
  if (!workingProxiesTxt || workingProxiesTxt != testWorkingProxiesTxt) {
    workingProxiesTxt = testWorkingProxiesTxt;
    const proxies = sortLinesByDate(workingProxiesTxt);
    const tbody = document.getElementById("wproxy");
    tbody.innerHTML = "";
    proxies.forEach((str) => {
      const tr = document.createElement("tr");
      const split = str.split("|");
      if (split.length < 7) {
        const remainingLength = 7 - split.length;
        for (let i = 0; i < remainingLength; i++) {
          split.push("undefined");
        }
      }
      split.forEach((info, i) => {
        const td = document.createElement("td");
        td.setAttribute(
          "class",
          "border-b border-slate-100 dark:border-slate-700 p-4 text-slate-500 dark:text-slate-400"
        );
        td.innerText = info;
        if (i == 0) {
          td.innerHTML += `<button class="rounded-full ml-2 pcopy" data="${info}"><i class="fa-duotone fa-copy"></i></button>`;
        } else if (i == 7 && info.length > 6) {
          // last check date
          td.innerText = timeAgo(info);
        } else {
          td.classList.add("text-center");
        }

        if (i == 5 || i == 6) {
          if (info.trim() == "-") {
            console.log(split[0], "missing geo location");
            fetch("./geoIp.php?proxy=" + split[0], { signal: AbortSignal.timeout(5000) }).catch(() => {
              //
            });
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
 * Sorts lines of text by date and reconstructs them.
 * @param {string} text - The text containing lines to be sorted.
 * @returns {string[]} The sorted lines of text.
 */
function sortLinesByDate(text) {
  /** @type {string[]} */
  const lines = text.split(/\r?\n/).filter((line) => line.trim() !== "");
  const original = lines.map((line) => {
    const parts = line.split("|");
    return {
      proxy: parts[0],
      latency: parts[1],
      type: parts[2],
      region: parts[3],
      city: parts[4],
      country: parts[5],
      timezone: parts[6],
      date: (parts[7] || "").trim()
    };
  });

  // Parse each line into objects
  let objects = lines.map((line) => {
    const parts = line.split("|");
    const date = (parts[7] || "").trim();
    return {
      proxy: parts[0],
      latency: parts[1],
      type: parts[2],
      region: parts[3],
      city: parts[4],
      country: parts[5],
      timezone: parts[6],
      date: date != "-" ? new Date(date) : new Date()
    };
  });

  // Sort objects based on date
  objects = objects.sort((a, b) => a.date - b.date).reverse();

  // Reconstruct sorted lines
  const sortedLines = objects.map((obj) => {
    const date = original.find((o) => o.proxy == obj.proxy).date;
    return `${obj.proxy}|${obj.latency}|${obj.type}|${obj.region}|${obj.city}|${obj.country}|${obj.timezone}|${date}`;
  });

  return sortedLines;
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
 * @param {string} message - The message to be displayed.
 */
function showSnackbar(message) {
  // Get the snackbar element
  var snackbar = document.getElementById("snackbar");

  // Set the message
  snackbar.textContent = message;

  // Add the "show" class to DIV
  snackbar.className = "show";

  // Hide the snackbar after 3 seconds
  setTimeout(function () {
    snackbar.className = snackbar.className.replace("show", "");
  }, 3000);
}

// Copies a string to the clipboard. Must be called from within an
// event handler such as click. May return false if it failed, but
// this is not always possible. Browser support for Chrome 43+,
// Firefox 42+, Safari 10+, Edge and Internet Explorer 10+.
// Internet Explorer: The clipboard feature may be disabled by
// an administrator. By default a prompt is shown the first
// time the clipboard is used (per session).
function copyToClipboard(text) {
  if (window.clipboardData && window.clipboardData.setData) {
    // Internet Explorer-specific code path to prevent textarea being shown while dialog is visible.
    return window.clipboardData.setData("Text", text);
  } else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
    var textarea = document.createElement("textarea");
    textarea.textContent = text;
    textarea.style.position = "fixed"; // Prevent scrolling to bottom of page in Microsoft Edge.
    document.body.appendChild(textarea);
    textarea.select();
    try {
      return document.execCommand("copy"); // Security exception may be thrown by some browsers.
    } catch (ex) {
      console.warn("Copy to clipboard failed.", ex);
      return prompt("Copy to clipboard: Ctrl+C, Enter", text);
    } finally {
      document.body.removeChild(textarea);
    }
  }
}

(function () {
  main();
})();
