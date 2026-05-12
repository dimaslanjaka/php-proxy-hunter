/**
 * Function to parse proxy data from a table.
 * @returns {Promise<any[]>} - A promise that resolves with an array of proxy data objects.
 */
export const parse_proxylistplus = function () {
  return new Promise(function (resolve) {
    // Select all table elements on the page
    const tables = Array.prototype.slice.call(document.querySelectorAll('table'));
    const ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
    const objectWrapper = [];

    // Loop through each table element using a for loop
    for (let i = 0; i < tables.length; i++) {
      const table = tables[i];
      const rows = Array.prototype.slice.call(table.querySelectorAll('tr'));

      for (let j = 0; j < rows.length; j++) {
        const row = rows[j];
        const td = Array.prototype.slice.call(row.querySelectorAll('td'));
        const texts = td
          .map(function (el) {
            return el.innerText;
          })
          .filter(function (str) {
            return typeof str === 'string' && str.trim().length > 0;
          });

        if (ipOnly.test(texts.join(' '))) {
          const item = {
            raw: texts[0] + ':' + texts[1],
            ip: texts[0],
            port: texts[1],
            type: texts[2],
            country: texts[3],
            anonymity: texts[4],
            https: texts[5]
          };
          objectWrapper.push(item);
        }
      }
    }

    resolve(objectWrapper);
  });
};
