/**
 * Function to parse proxy data from the document.
 * @returns A promise that resolves with an array of proxy data objects.
 */
export function parse_proxy_db_net() {
  return new Promise(function (resolve) {
    const regex = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{2,5})/;
    const result = [];
    const a = Array.prototype.slice.call(document.getElementsByClassName('spy14'));

    for (let outerLoopIndex = 0; outerLoopIndex < a.length; outerLoopIndex++) {
      // Renamed outer loop variable
      if (a[outerLoopIndex].innerText.includes(':')) {
        result.push({ raw: a[outerLoopIndex].innerText });
      }
    }

    const tables = Array.prototype.slice.call(document.querySelectorAll('table'));
    for (let tableLoopIndex = 0; tableLoopIndex < tables.length; tableLoopIndex++) {
      // Renamed outer loop variable for tables
      const table = tables[tableLoopIndex];
      const tr = Array.prototype.slice.call(table.querySelectorAll('tr'));
      for (let i = 0; i < tr.length; i++) {
        // Inner loop variable remains i
        const td = Array.prototype.slice.call(tr[i].querySelectorAll('td'));
        if (td[0]) {
          const test = regex.test(td[0].innerText);
          if (test) result.push({ raw: td[0].innerText });
        }
      }
    }

    resolve(result);
  });
}
