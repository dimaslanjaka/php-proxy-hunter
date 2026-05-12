/**
 * Function to parse the first and second row proxy data from a table.
 * @returns {Promise<any[]>} - A promise that resolves with an array of proxy data objects.
 */
export const parse_first_and_second_row = function (): Promise<any[]> {
  return new Promise(function (resolve) {
    const tables = Array.prototype.slice.call(document.querySelectorAll('table'));
    const ipOnly = /(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/gm;
    const objectWrapper = [];

    for (let i = 0; i < tables.length; i++) {
      const table = tables[i];
      const rows = Array.prototype.slice.call(table.querySelectorAll('tr'));

      for (let j = 0; j < rows.length; j++) {
        const row = rows[j];
        const buildObject = {
          raw: null,
          code: null,
          anonymity: null,
          ssl: null,
          google: null,
          alert: null,
          type: 'http',
          test: null
        };
        const td = row.querySelectorAll('td');
        const proxy = td[0];
        const port = td[1];
        const countryCode = td[2];
        const anonymity = td[4];
        const google = td[5];
        const ssl = td[6];

        if (proxy && ssl && ipOnly.test(proxy.innerText)) {
          buildObject.raw = proxy.innerText.trim() + ':' + port.innerText.trim();
          buildObject.google = /^yes/.test(google.innerText.trim()) ? true : false;
          buildObject.ssl = /^yes/.test(ssl.innerText.trim()) ? true : false;
          buildObject.code = countryCode.innerText.trim();

          switch (anonymity.innerText.trim()) {
            case 'elite proxy':
              buildObject.anonymity = 'H';
              break;
            case 'anonymous':
              buildObject.anonymity = 'A';
              break;
            default:
              buildObject.anonymity = 'N';
              break;
          }

          objectWrapper.push(buildObject);
        }
      }
    }

    resolve(objectWrapper);
  });
};
