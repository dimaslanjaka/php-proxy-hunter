/**
 * Function to parse IP:PORT from the first row.
 * @returns {Promise<any[]>} - A promise that resolves with an array of IP:PORT data objects.
 */
export const parse_first_row_ip_port = function (): Promise<any[]> {
  return new Promise(function (resolve) {
    const regex = /^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})$/;
    const result = [];
    const spy14Elements = Array.prototype.slice.call(document.getElementsByClassName('spy14'));

    for (let i = 0; i < spy14Elements.length; i++) {
      if (spy14Elements[i].innerText.includes(':')) {
        result.push({ raw: spy14Elements[i].innerText });
      }
    }

    const tables = Array.prototype.slice.call(document.querySelectorAll('table'));
    for (let j = 0; j < tables.length; j++) {
      const table = tables[j];
      const trElements = Array.prototype.slice.call(table.querySelectorAll('tr'));

      for (let k = 0; k < trElements.length; k++) {
        const tdElements = Array.prototype.slice.call(trElements[k].querySelectorAll('td'));
        if (tdElements.length > 0 && regex.test(tdElements[0].innerText)) {
          result.push({ raw: tdElements[0].innerText });
        }
      }
    }

    resolve(result);
  });
};
