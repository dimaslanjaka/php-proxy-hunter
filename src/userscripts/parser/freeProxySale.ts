import { findIPv4Addresses } from './findIPv4Addresses';

/**
 * free.proxy-sale.com parser
 * * extract only IP
 * @returns {Promise<{ raw: string }[]>}
 */
export const freeProxySale = function (): Promise<{ raw: string }[]> {
  return new Promise(function (resolve) {
    const result: { raw: string }[] = [];
    const proxyTable = document.querySelectorAll('.proxy__table');
    proxyTable.forEach(function (wrapper) {
      Array.from(wrapper.querySelectorAll('[class^=css-]')).forEach(function (el) {
        const ips = findIPv4Addresses(el.textContent || '');
        if (ips.length > 0) {
          ips.forEach(function (ip) {
            result.push({ raw: ip + ':80' });
            result.push({ raw: ip + ':443' });
            result.push({ raw: ip + ':8080' });
            result.push({ raw: ip + ':8000' });
          });
        }
      });
    });
    resolve(result);
  });
};
