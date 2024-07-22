let prevOutput = "";
let processedOutput = "";
function processText(info) {
  // skip update UI when output when remains same
  if (prevOutput === info) return processedOutput;
  if (typeof info !== "string") return processedOutput;
  if (info.trim().length === 0) return processedOutput;
  prevOutput = info || "";
  processedOutput = (info || "")
    .split(/\r?\n/)
    .map((str) => {
      // remove ANSI codes
      // eslint-disable-next-line no-control-regex
      str = str.replace(/\x1b\[[0-9;]*m/g, "");
      str = str.replace(/port closed/gm, '<span class="text-red-400">port closed</span>');
      str = str.replace(/port open/gm, '<span class="text-green-400">port open</span>');
      str = str.replace(/not working/gm, '<span class="text-red-600">not working</span>');
      str = str.replace(/dead/gm, '<span class="text-red-600">dead</span>');
      str = str.replace(
        /(\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{1,5}\b)\s+invalid/g,
        '$1 <span class="text-red-600">invalid</span>'
      );
      str = str.replace(
        /(\badd\b)\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{1,5})/g,
        '<span class="text-green-400">add</span> $2'
      );
      if (/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{1,5}/.test(str)) {
        str = str.replace(/working.*/, (whole) => {
          if (whole.includes("-1")) return `<span class="text-orange-400">${whole}</span>`;
          return `<span class="text-green-400">${whole}</span>`;
        });
      }
      str = str.replace(/\[DELETED\]/, '<i class="fal fa-trash text-red-400"></i>');
      str = str.replace(/\[SKIPPED\]/, '<i class="fal fa-forward text-silver"></i>');
      str = str.replace(/\[RESPAWN\]/, '<i class="fa-solid fa-user-magnifying-glass text-magenta"></i>');
      str = str.replace(/\[FILTER-PORT\]/, '<i class="fa-thin fa-filter-list text-berry"></i>');
      str = str.replace(/\[CHECKER-PARALLEL\]/, '<i class="fa-thin fa-list-check text-polkador"></i>');
      str = str.replace(/\[CHECKER\]/, '<i class="fa-thin fa-check-to-slot text-polkador"></i>');
      return str;
    })
    .join("<br/>");
  return processedOutput;
}

// Scroll to the bottom if the content is already at the bottom
function scrollToBottomIfNeeded(css_selector) {
  const scrollable_element = document.querySelector(css_selector);
  const top = scrollable_element.scrollHeight - scrollable_element.scrollTop - 50;
  const height = scrollable_element.clientHeight;
  // console.log({ top, height });
  if (top <= height) {
    scrollable_element.scrollTop = scrollable_element.scrollHeight;
  }
}

// Function to fetch data and update the textarea
async function fetchDataAndUpdateTextarea() {
  try {
    const response = await fetch("/proxy/result?format=txt&date=" + new Date());
    const data = await response.text();
    const el = document.getElementById("resultTextarea");
    const new_data = processText(data);
    if (new_data) el.innerHTML = new_data;

    // Call the function on content update
    scrollToBottomIfNeeded("#resultTextarea");

    // Optional: Handle window resize events
    window.addEventListener("resize", () => scrollToBottomIfNeeded("#resultTextarea"));
  } catch (error) {
    console.error("Error fetching data:", error);
  }
}

// Set interval to fetch data every [n] seconds
setInterval(fetchDataAndUpdateTextarea, 3 * 1000);

document.getElementById("filter-ports-duplicate").addEventListener("click", (e) => {
  e.preventDefault();
  fetch("/proxy/filter?date=" + new Date());
});

document.getElementById("re-check-random").addEventListener("click", (e) => {
  e.preventDefault();
  fetch("/proxy/check?date=" + new Date());
});

// Optional: Fetch data immediately on page load
window.onload = function () {
  fetchDataAndUpdateTextarea();
};
