/**
 * Function to parse the second and third row proxy data from a table.
 * @returns {Promise<any[]>} - A promise that resolves with an array of proxy data objects.
 */
export const parse_second_and_third_row = function (): Promise<any[]> {
  return new Promise(function (resolve) {
    const tables = Array.prototype.slice.call(document.querySelectorAll('table'));
    const ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
    const objectWrapper = [];

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
          objectWrapper.push({
            raw: texts[1] + ':' + texts[2],
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
