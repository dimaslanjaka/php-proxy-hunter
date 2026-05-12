import { encryptStr, isValidEncryptStr } from '../utils/encryption';
import { splitStringByLines } from './splitStringByLines';

/**
 * Upload and check proxy.
 * @param dataToSend - The proxy data to send.
 */
export const addProxyFun = function (dataToSend: any) {
  if (!dataToSend) return;
  if (typeof dataToSend !== 'string') dataToSend = JSON.stringify(dataToSend, null, 2);

  /**
   * Check if the data has already been sent by looking at local storage.
   * @param data - The data to check.
   * @returns True if the data has already been sent.
   */
  const hasDataBeenSent = function (data: any) {
    let processedData = data || '';
    if (typeof processedData !== 'string') processedData = encryptStr(JSON.stringify(processedData));
    if (!isValidEncryptStr(processedData)) processedData = encryptStr(processedData);
    const sentData = localStorage.getItem('sentData');
    const result = sentData && sentData.includes(processedData);
    console.log(processedData, 'is same', result);
    return result;
  };

  /**
   * Mark data as sent by saving it in local storage.
   * @param data - The data to be marked as sent.
   */
  const markDataAsSent = function (data: any) {
    // skip null data
    if (!data) return;

    // Check if data has already been sent
    if (!hasDataBeenSent(data)) {
      let processedData = data;
      if (typeof processedData !== 'string') {
        processedData = encryptStr(JSON.stringify(processedData)); // Convert object data to MD5 hash
      }
      if (!isValidEncryptStr(processedData)) {
        processedData = encryptStr(processedData); // Ensure data is in MD5 format
      }

      try {
        let sentData = localStorage.getItem('sentData') || '';
        sentData += processedData + '\n'; // Append the entire data
        localStorage.setItem('sentData', sentData);
      } catch (_e) {
        console.log('RESET LOCAL STORAGE DATA');
        // reset local storage
        localStorage.setItem('sentData', processedData);
      }
    }
  };

  if (hasDataBeenSent(dataToSend)) return;

  const services = [
    // php proxy hunter
    'http://localhost/proxyAdd.php',
    'http://localhost/proxyCheckerParallel.php',
    'https://sh.webmanajemen.com/proxyAdd.php',
    'https://sh.webmanajemen.com/proxyCheckerParallel.php',
    'https://sh.webmanajemen.com/php_backend/proxy-add.php',
    // python proxy hunter
    'https://sh.webmanajemen.com:8443/proxy/check',
    'https://localhost:4000/proxy/check',
    'https://localhost:7000/proxy/check',
    'https://localhost:8000/proxy/check'
  ];

  /**
   * Perform fetch with a delay.
   * @param url - The URL to which the fetch request is made.
   * @param dataToSend - The data to be sent in the POST request.
   * @returns A promise that resolves after the fetch completes.
   */
  const fetchWithDelay = async function (url: string, dataToSend: string): Promise<void> {
    await new Promise(function (resolve) {
      setTimeout(resolve, 1000); // 1 second delay
    });

    try {
      const response = await fetch(url, {
        signal: AbortSignal.timeout(5000),
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Greasemonkey-Script': '1' },
        body: dataToSend
      });

      if (!response.ok) {
        const headers: { name: string; value: string }[] = [];
        response.headers.forEach(function (value, name) {
          headers.push({ name: name, value: value });
        });
        const body = await response.text();
        throw {
          status: response.status + ' ' + response.statusText,
          message: 'Network response to ' + url + ' was not ok',
          headers: headers,
          body: body
        };
      }

      const data = await response.text();
      console.log(data);
    } catch (error: any) {
      if (error.status) throw error; // Re-throw network response errors
      throw {
        message: 'There was a problem with your fetch operation: (' + (error.message || error) + ')'
      };
    }
  };

  services.forEach(function (url) {
    /**
     * Upload proxy data to the specified service.
     * @param str_data - The proxy data string to upload.
     */
    const do_upload = function (str_data: string) {
      fetchWithDelay(url, 'proxy=' + encodeURIComponent(str_data))
        .then(function () {
          return fetchWithDelay(url, 'proxies=' + encodeURIComponent(str_data));
        })
        .catch(function (error) {
          console.error('Failed to fetch with delay:', error);
        });
    };

    const split_body = splitStringByLines(dataToSend, 100);
    if (url.indexOf('proxyCheckerParallel') === -1) {
      split_body.forEach(do_upload);
    } else {
      const item = split_body[Math.floor(Math.random() * split_body.length)];
      do_upload(item);
    }
    markDataAsSent(dataToSend);
  });
};
