function init_search() {
  const searchInput = document.getElementById("searchInput");
  // Get the URL parameters
  const urlParams = new URLSearchParams(window.location.search);

  // Get the 'search' parameter
  const searchParam = urlParams.get("search");

  // If the 'search' parameter exists, set it as the value of the input field
  if (searchParam !== null) {
    searchInput.value = searchParam;
  }
}

function init_table() {
  const tbody = document.querySelector("#proxy-items");
  tbody.innerHTML = "";

  // Step 1: Get the current URL's query parameters
  const queryString = window.location.search;

  // Step 2: Parse the query string into a URLSearchParams object
  const params = new URLSearchParams(queryString);

  // Step 3: Modify the parameters
  // params.set("newParam", "newValue"); // Add or modify parameters
  // params.delete("oldParam"); // Remove parameters
  params.set("max", "30");
  // if (!params.has("status")) {
  //   params.set("status", "active");
  // }

  // console.log(params);

  // Step 4: Convert the parameters back into a query string
  const updatedQueryString = params.toString();

  // Step 5: Use the updated query string in the fetch request
  fetch(`/proxy/list?${updatedQueryString}`)
    .then((response) => response.json())
    .then(
      /**
       *
       * @param {Record<string, any>[]} items
       */
      (items) => {
        // Handle the response data
        if (items.length > 0) {
          for (let i = 0; i < items.length; i++) {
            const item = items[i];
            const tr = document.createElement("tr");
            tr.classList.add(..."border-b border-gray-200".split(" "));
            // add background color based on proxy status
            if (item["status"] == "active") {
              tr.classList.add("proxy-active");
            } else if (item["status"] == "untested") {
              tr.classList.add("proxy-untested");
            } else {
              tr.classList.add("proxy-dead");
            }
            // create badge on first column
            const td_badge = document.createElement("td");
            td_badge.classList.add(..."py-3 px-6 text-left whitespace-nowrap overflow-hidden text-ellipsis".split(" "));
            let div = '<div class="flex flex-wrap space-x-1">';
            if (item["https"] == "true") {
              div +=
                '<a class="bg-green-100 text-green-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-green-400 mb-1" href="/proxy?https=true">SSL</a>';
            }
            const protocols = ("" + (item["type"] || "")).split("-");
            for (let i = 0; i < protocols.length; i++) {
              const protocol = protocols[i];
              if (protocol.toLowerCase() == "http") {
                div +=
                  '<a class="bg-blue-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-blue-400 mb-1" href="/proxy?type=http">HTTP</a>';
              } else if (protocol.toLowerCase() == "socks4") {
                div +=
                  '<a class="bg-green-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-green-400 mb-1" href="/proxy?type=socks4">SOCKS4</a>';
              } else if (protocol.toLowerCase() == "socks5") {
                div +=
                  '<a class="bg-red-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-red-400 mb-1" href="/proxy?type=socks5">SOCKS5</a>';
              }
            }
            td_badge.innerHTML = div;
            tr.appendChild(td_badge);
            const exclude = ["id", "type", "status", "https"];
            for (const key in item) {
              if (exclude.includes(key)) continue;
              let val = item[key] || "-";
              if (val.toString().length === 0) val = "-";
              const td = document.createElement("td");
              td.classList.add(..."py-3 px-6 text-left whitespace-nowrap overflow-hidden text-ellipsis".split(" "));
              if (key == "last_check" && val.length > 10) {
                // last_check column
                val = timeAgo(val);
              } else {
                val = `<a href="/proxy/?${key}=${val}">${val}</a>`;
              }
              td.innerHTML = val;
              tr.appendChild(td);
            }
            tbody.appendChild(tr);
            // fetch geolocation
            if ((!item["timezone"] || !item["lang"]) && item["status"] == "active") {
              // console.log(item["proxy"], "missing geolocation");
              fetch(`/proxy/geolocation/${item["proxy"]}`);
            }
          }
        } else {
          // empty proxy
          const tr = document.createElement("tr");
          const td = document.createElement("td");
          td.colSpan = 21;
          td.innerHTML = "No proxies found.";
          td.classList.add(..."py-3 px-6 text-center".split(" "));
          tr.appendChild(td);
          tbody.appendChild(tr);
        }
      }
    )
    .catch((error) => {
      // Handle any errors
      console.error("Error:", error);
    });
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

setTimeout(() => {
  init_search();
  init_table();
}, 1500);
