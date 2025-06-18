/* eslint-disable no-control-regex */
import { extractJson } from '../../../../src/utils/string.js';

let isFetchingLogs = false;
let lastResponse = null;

/**
 * Fetch and render logs to the page
 */
function fetchLogs() {
  if (isFetchingLogs) return;
  isFetchingLogs = true;

  fetch('/user/logs-get')
    .then((response) => response.text())
    .then((data) => {
      if (lastResponse === data) return; // Avoid duplicate updates
      lastResponse = data;

      const logsDiv = document.getElementById('logs');
      if (!logsDiv) return;

      /** @type {string[]} */
      const lines = data.split('\n');
      /** @type {Set<string>} */
      const seen = new Set();
      /** @type {string[]} */
      const uniqueLines = [];
      /** @type {number[]} */
      const removedIndexes = [];

      // Remove duplicates (keep last occurrence)
      for (let i = lines.length - 1; i >= 0; i--) {
        const line = lines[i].trim();
        if (!line) continue;
        if (!seen.has(line)) {
          seen.add(line);
          uniqueLines.unshift(line);
        } else {
          removedIndexes.unshift(i);
        }
      }

      // Insert placeholder for removed items
      removedIndexes.forEach((i) => uniqueLines.splice(i, 0, '[removed]'));

      // Define static highlight and icon rules
      const highlights = [
        { regex: /port closed/g, replace: '<span class="text-red-400">port closed</span>' },
        { regex: /port open/g, replace: '<span class="text-green-400">port open</span>' },
        { regex: /not working/g, replace: '<span class="text-red-600">not working</span>' },
        { regex: /dead/g, replace: '<span class="text-red-600">dead</span>' },
        {
          regex: /(\b\d{1,3}(?:\.\d{1,3}){3}:\d{1,5})\s+invalid/g,
          replace: '$1 <span class="text-red-600">invalid</span>'
        },
        {
          regex: /\badd\b\s+(\d{1,3}(?:\.\d{1,3}){3}:\d{1,5})/g,
          replace: '<span class="text-green-400">add</span> $1'
        }
      ];

      const iconMap = [
        { regex: /\[LIST-DUPLICATE\]/g, icon: '<i class="fa fa-list-ul" aria-hidden="true"></i>' },
        { regex: /\[CHECK-DUPLICATE\]/g, icon: '<i class="fa fa-check-double" aria-hidden="true"></i>' },
        { regex: /\[DELETED\]/g, icon: '<i class="fal fa-trash text-red-400"></i>' },
        { regex: /\[SKIPPED\]/g, icon: '<i class="fal fa-forward text-silver"></i>' },
        { regex: /\[RESPAWN\]/g, icon: '<i class="fa-solid fa-user-magnifying-glass text-magenta"></i>' },
        { regex: /\[FILTER-PORT\]/g, icon: '<i class="fa-thin fa-filter-list text-berry"></i>' },
        { regex: /\[CHECKER-PARALLEL\]/g, icon: '<i class="fa-thin fa-list-check text-polkador"></i>' },
        { regex: /\[CHECKER\]/g, icon: '<i class="fa-thin fa-check-to-slot text-polkador"></i>' },
        { regex: /\[SQLite\]/g, icon: '<i class="fa-thin fa-database text-polkador"></i>' }
      ];

      // Format each line
      for (let i = 0; i < uniqueLines.length; i++) {
        let line = uniqueLines[i];

        // Remove ANSI color codes
        line = line.replace(/\x1b\[[0-9;]*m/g, '');

        // Apply keyword highlights
        highlights.forEach(({ regex, replace }) => {
          line = line.replace(regex, replace);
        });

        // IP:Port "working" special highlight
        if (/\d{1,3}(?:\.\d{1,3}){3}:\d{1,5}/.test(line)) {
          line = line.replace(/working.*/, (whole) =>
            whole.includes('-1')
              ? `<span class="text-orange-400">${whole}</span>`
              : `<span class="text-green-400">${whole}</span>`
          );
        }

        // Replace custom tags with icons
        iconMap.forEach(({ regex, icon }) => {
          line = line.replace(regex, icon);
        });

        // Try extract JSON and pretty print
        const extract = extractJson(line);
        if (extract) {
          line = line.replace(
            extract.raw,
            `<pre class="hljs"><code class="language-json">${JSON.stringify(extract.parsed, null, 2)}</code></pre>`
          );
        }

        uniqueLines[i] = line;
      }

      logsDiv.innerHTML = uniqueLines.join('\n');
    })
    .catch((error) => {
      console.error('Error fetching logs:', error);
    })
    .finally(() => {
      isFetchingLogs = false;
    });
}

// Start fetching logs on page load and refresh periodically
document.addEventListener('DOMContentLoaded', function () {
  fetchLogs();
  setInterval(fetchLogs, 3000);
  const clearBtn = document.getElementById('clear-logs-btn');
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      fetch('/user/logs-clear', { method: 'POST' })
        .then(() => fetchLogs())
        .catch((err) => console.error('Error clearing logs:', err));
    });
  }
});
