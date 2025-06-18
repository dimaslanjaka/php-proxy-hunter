/* eslint-disable no-control-regex */
import { extractJson } from '../../../../src/utils/string.js';

let isFetchingLogs = false;

function fetchLogs() {
  if (isFetchingLogs) return;
  isFetchingLogs = true;

  fetch('/user/logs-get')
    .then((response) => response.text())
    .then((data) => {
      const logsDiv = document.getElementById('logs');
      if (logsDiv) {
        // Split data into lines
        const lines = data.split('\n');
        // Remove duplicates, keeping the last occurrence
        const seen = new Set();
        const uniqueLines = [];
        const removedIndexes = [];
        for (let i = lines.length - 1; i >= 0; i--) {
          const line = lines[i];
          if (!seen.has(line) && line.trim() !== '') {
            seen.add(line);
            uniqueLines.unshift(line);
          } else if (line.trim() !== '') {
            removedIndexes.unshift(i);
          }
        }
        // Insert placeholder for removed items
        removedIndexes.forEach((idx) => {
          uniqueLines.splice(idx, 0, '[removed]');
        });
        // Replace [LIST-DUPLICATE] and [CHECK-DUPLICATE] with Font Awesome icons
        for (let i = 0; i < uniqueLines.length; i++) {
          // Remove ANSI codes
          uniqueLines[i] = uniqueLines[i].replace(/\x1b\[[0-9;]*m/g, '');

          // Highlight keywords and patterns
          const highlights = [
            { regex: /port closed/gm, replace: '<span class="text-red-400">port closed</span>' },
            { regex: /port open/gm, replace: '<span class="text-green-400">port open</span>' },
            { regex: /not working/gm, replace: '<span class="text-red-600">not working</span>' },
            { regex: /dead/gm, replace: '<span class="text-red-600">dead</span>' },
            {
              regex: /(\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{1,5}\b)\s+invalid/g,
              replace: '$1 <span class="text-red-600">invalid</span>'
            },
            {
              regex: /(\badd\b)\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{1,5})/g,
              replace: '<span class="text-green-400">add</span> $2'
            }
          ];
          highlights.forEach(({ regex, replace }) => {
            uniqueLines[i] = uniqueLines[i].replace(regex, replace);
          });

          // Special handling for "working" lines with IP:port
          if (/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{1,5}/.test(uniqueLines[i])) {
            uniqueLines[i] = uniqueLines[i].replace(/working.*/, (whole) =>
              whole.includes('-1')
                ? `<span class="text-orange-400">${whole}</span>`
                : `<span class="text-green-400">${whole}</span>`
            );
          }

          // Replace tags with Font Awesome icons
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
          iconMap.forEach(({ regex, icon }) => {
            uniqueLines[i] = uniqueLines[i].replace(regex, icon);
          });
          const extract = extractJson(uniqueLines[i]);
          if (extract) {
            uniqueLines[i] = uniqueLines[i].replace(
              extract.raw,
              `<pre class="hljs"><code class="language-json">${JSON.stringify(extract.parsed, null, 2)}</code></pre>`
            );
          }
        }
        logsDiv.innerHTML = uniqueLines.join('\n');
      }
    })
    .catch((error) => {
      console.error('Error fetching logs:', error);
    })
    .finally(() => {
      isFetchingLogs = false;
    });
}

document.addEventListener('DOMContentLoaded', function () {
  fetchLogs();
  setInterval(fetchLogs, 3000);
});
