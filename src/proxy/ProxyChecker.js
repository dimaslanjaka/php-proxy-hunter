import ansiColors from 'ansi-colors';
import { load as loadCheerio } from 'cheerio';
import { TypedEmitter } from 'tiny-typed-emitter';
import { toMs } from '../../node_browser/puppeteer/time_utils.js';
import { buildCurl } from '../utils/curl.js';
import { getAnonymity } from './anonymity.js';
import utils, { CheckerResult, isPortOpen } from './utils.js';

/**
 * @typedef {Object} ProxyCheckerEvents
 * @property {(message: string) => void} debug - Debug messages during proxy checks.
 * @property {(proxyAddress: string) => void} ip - Emitted with information about the device and proxy IPs.
 * @property {(message: string) => void} title - Emitted with the title check results.
 */

/**
 * Proxy Checker Class for validating proxy addresses.
 * @extends {TypedEmitter<ProxyCheckerEvents>}
 */
export class ProxyChecker extends TypedEmitter {
  /**
   * Accurate validation.
   * true = will match title equals, ip device and ip proxy should be different
   */
  accurate = true;
  constructor() {
    super();
  }

  /**
   * Check a single proxy with a given protocol and IP:PORT string.
   * Emits debug messages using 'debug' and 'ip' events.
   * @param {'http'|'https'|'socks4'|'socks5'} protocol - The proxy protocol.
   * @param {string} proxyAddress - The proxy in IP:PORT format.
   * @param {number} [repeatCount=0] - Internal count for retry attempts, defaults to 0.
   * @param {string} [titleShouldBe='SSL Certificate Checker'] - The expected title of the target page.
   * @param {string} [endpoint='https://www.ssl.org/'] - The endpoint URL to check the proxy against.
   * @param {boolean} [checkHttpOnly=false] - Whether to recheck using HTTP instead of HTTPS when SSL fails.
   * @returns {Promise<import('./utils.js').CheckerResult>} - Resolves to a `CheckerResult` object.
   */
  async checkProxySingle(
    protocol,
    proxyAddress,
    titleShouldBe = 'SSL Certificate Checker',
    endpoint = 'https://www.ssl.org/',
    checkHttpOnly = false,
    repeatCount = 0
  ) {
    const innerCheck = (url) => buildCurl(url, { protocol, address: proxyAddress });
    try {
      const [proxyHost, proxyPort] = proxyAddress.split(':');
      if (!proxyHost || !proxyPort) {
        throw new Error('Invalid proxy address format. Use IP:PORT.');
      }

      const response = await innerCheck(endpoint);
      this.emit('status', `${protocol}://${proxyAddress} ${response.statusText}`);
      const $ = loadCheerio(response.data);
      const title = $('title').text();
      let titleMatched = false;
      if (typeof title === 'string' && typeof titleShouldBe === 'string') {
        titleMatched = title.toLowerCase().includes(titleShouldBe.toLowerCase());
        this.emit('debug', `${protocol}://${proxyAddress} ${titleShouldBe} [${titleMatched ? 'success' : 'failed'}]`);
        this.emit(
          'title',
          `${protocol}://${proxyAddress} Matched: ${titleShouldBe} [${titleMatched ? 'success' : 'failed'}]. Response title: ${title.length > 0 ? title : '<empty string>'}.`
        );
      }

      const deviceIp = await utils.getPublicIP();
      let proxyIp;
      try {
        proxyIp = (await innerCheck('https://api.ipify.org?format=json')).data.ip;
      } catch (_) {
        try {
          proxyIp = (await innerCheck('https://ipinfo.io/json')).data.ip;
        } catch (_) {
          try {
            proxyIp = (await innerCheck('https://ifconfig.me/all.json')).data.ip_addr;
          } catch (_) {
            //
          }
        }
      }
      const publicIpValid = deviceIp !== proxyIp && typeof proxyIp === 'string' && typeof deviceIp === 'string';

      this.emit(
        'debug',
        `${protocol}://${proxyAddress} IP device: ${deviceIp}, proxy: ${proxyIp}, ${publicIpValid ? 'valid' : 'invalid'}`
      );
      this.emit(
        'ip',
        `${protocol}://${proxyAddress} IP device: ${deviceIp}, proxy: ${proxyIp}, ${publicIpValid ? 'valid' : 'invalid'}`
      );

      if (titleMatched && publicIpValid && this.accurate) {
        // accurated
        return new utils.CheckerResult(true, endpoint.startsWith('https://'));
      } else if (!this.accurate && (titleMatched || publicIpValid)) {
        // inaccurated
        return new utils.CheckerResult(true, endpoint.startsWith('https://'));
      } else {
        return new utils.CheckerResult(false, false);
      }
    } catch (error) {
      const missCheck = ['Client network socket disconnected before secure TLS connection was established'].map((s) =>
        s.toLowerCase()
      );

      if (missCheck.includes(error.message.toLowerCase()) && repeatCount < 4) {
        return this.checkProxySingle(protocol, proxyAddress, titleShouldBe, endpoint, checkHttpOnly, repeatCount++);
      } else if (['400', 'bad request'].includes(error.message.toLowerCase()) && repeatCount < 8 && checkHttpOnly) {
        return this.checkProxySingle(
          protocol,
          proxyAddress,
          'http forever',
          'http://httpforever.com/',
          checkHttpOnly,
          repeatCount++
        );
      }

      const errorMessage = error.message.replace(
        /ETIMEDOUT|ECONNREFUSED|ECONNRESET|EHOSTUNREACH|ENOTFOUND|EADDRINUSE|EPIPE|ECONNABORTED|ETXTBSY|Invalid/gim,
        (match) => ansiColors.redBright(match)
      );
      this.emit('debug', `${protocol}://${proxyAddress}: ${errorMessage}`);
      return new utils.CheckerResult(false, false, `${protocol}://${proxyAddress}: ${errorMessage}`);
    }
  }

  /**
   * Check proxy from object return `extractProxiesToObject`
   * @param {ReturnType<typeof import('./extractor.js')['extractProxiesToObject']>[number]} proxyData
   */
  async checkProxyObject(proxyData, titleShouldBe = 'SSL Certificate Checker', endpoint = 'https://www.ssl.org/') {
    const protocols = ['http', 'socks4', 'socks5'];
    /**
     * Holds the results of proxy checks, categorized by protocol type.
     *
     * @type {Record<'http' | 'socks4' | 'socks5', import('./utils.js').CheckerResult>}
     */
    const results = {};
    for (let i = 0; i < protocols.length; i++) {
      const protocol = protocols[i];
      const format = `${protocol}://${proxyData.address}`;
      const greenFormat = ansiColors.green(format);
      const redFormat = ansiColors.red(format);
      const innerCheck = (url) => buildCurl(url, { ...proxyData, protocol }, toMs(60));

      try {
        const curl = await innerCheck(endpoint);
        if (typeof curl.data === 'string') {
          if (curl.data.includes('HTTP_')) {
            // azenv proxy (outbound traffic on this protocol not working)
            this.emit('debug', ansiColors.red(format) + ' outbound traffic failed');
          } else {
            const $ = loadCheerio(curl.data);
            const title = $('title').text();
            // console.log(ansiColors.green(format), curl.status, title);
            let titleMatched = false;
            if (typeof title === 'string' && typeof titleShouldBe === 'string') {
              titleMatched = title.toLowerCase().includes(titleShouldBe.toLowerCase());
              this.emit('debug', `${greenFormat} ${titleShouldBe} [${titleMatched ? 'success' : 'failed'}]`);
              this.emit(
                'title',
                `${greenFormat} Matched: ${titleShouldBe} [${titleMatched ? 'success' : 'failed'}]. Response title: ${title.length > 0 ? title : '<empty string>'}.`
              );
            }

            const deviceIp = await utils.getPublicIP();
            let proxyIp;
            try {
              proxyIp = (await innerCheck('https://api.ipify.org?format=json')).data.ip;
            } catch (_) {
              try {
                proxyIp = (await innerCheck('https://ipinfo.io/json')).data.ip;
              } catch (_) {
                try {
                  proxyIp = (await innerCheck('https://ifconfig.me/all.json')).data.ip_addr;
                } catch (_) {
                  //
                }
              }
            }
            const publicIpValid = deviceIp !== proxyIp && typeof proxyIp === 'string' && typeof deviceIp === 'string';

            this.emit(
              'debug',
              `${greenFormat} IP device: ${deviceIp}, proxy: ${proxyIp}, ${publicIpValid ? 'valid' : 'invalid'}`
            );
            this.emit(
              'ip',
              `${greenFormat} IP device: ${deviceIp}, proxy: ${proxyIp}, ${publicIpValid ? 'valid' : 'invalid'}`
            );

            if (titleMatched && publicIpValid) {
              // accurated
              const result = new utils.CheckerResult(true, endpoint.startsWith('https://'));
              results[protocol] = result;
            }
          }
        } else {
          this.emit('debug', greenFormat, curl.status, curl.data);
        }
      } catch (error) {
        let message;
        if (error.message) {
          message = error.message;
        } else if (error.code) {
          message = 'http response ' + error.code;
        }
        this.emit('debug', `${redFormat} ${message}`);
      }
    }
    return results;
  }

  /**
   * Checks the validity of a proxy for different protocols (HTTP, HTTPS, SOCKS4, SOCKS5).
   * Emits debug messages for each protocol check.
   * @param {string} proxyAddress - The proxy address in IP:PORT format.
   * @param {string} [titleShouldBe='SSL Certificate Checker'] - The expected title of the target page.
   * @param {string} [endpoint='https://www.ssl.org/'] - The endpoint URL to check the proxy against.
   * @param {boolean} [checkHttpOnly=false] - Whether to recheck using HTTP instead of HTTPS when SSL fails.
   * @returns {Promise<Record<'http'|'https'|'socks4'|'socks5', import('./utils.js').CheckerResult>>} -
   * A promise that resolves to an object where each key (protocol) maps to a `CheckerResult` object.
   */
  async checkProxy(
    proxyAddress,
    titleShouldBe = 'SSL Certificate Checker',
    endpoint = 'https://www.ssl.org/',
    checkHttpOnly = false
  ) {
    if (!proxyAddress) throw new Error('Proxy address is empty');
    if (!(await isPortOpen(proxyAddress))) {
      return {
        http: new CheckerResult(false, false, `${proxyAddress} port closed`),
        https: new CheckerResult(false, false, `${proxyAddress} port closed`),
        socks5: new CheckerResult(false, false, `${proxyAddress} port closed`),
        socks4: new CheckerResult(false, false, `${proxyAddress} port closed`)
      };
    }

    return {
      // http or https
      http: await this.checkProxySingle('http', proxyAddress, titleShouldBe, endpoint, checkHttpOnly),
      socks4: await this.checkProxySingle('socks4', proxyAddress, titleShouldBe, endpoint, checkHttpOnly),
      socks5: await this.checkProxySingle('socks5', proxyAddress, titleShouldBe, endpoint, checkHttpOnly)
    };
  }

  /**
   * Checks the result of a proxy and determines its validity, protocols, SSL status, and anonymity level.
   * @param {string} proxy - The proxy address to check.
   * @returns {Promise<{isValid: boolean, protocols: string[], isSSL: boolean, anonymity: string, error: (string|Error)[]}>}
   * Resolves to an object containing the validity and other details about the proxy.
   */
  async checkProxyResult(proxy) {
    const results = await this.checkProxy(proxy);
    const onlyActive = Object.fromEntries(Object.entries(results).filter(([_key, value]) => value.result === true));
    let isValid = Object.keys(onlyActive).length > 0;
    let protocols = Object.keys(onlyActive);

    // Try to find only HTTPS
    const onlyHttps = Object.fromEntries(
      Object.entries(results).filter(([_key, value]) => value.result === true && value.https === true)
    );
    const isSSL = Object.keys(onlyHttps).length > 0;
    if (isSSL) {
      isValid = Object.keys(onlyHttps).length > 0;
      protocols = Object.keys(onlyHttps);
    }
    let anonymity = 'Transparent';
    if (isValid) {
      let anonymities = await getAnonymity(proxy);
      if (anonymities) {
        if (anonymities.some((o) => o.anonymity.toLocaleLowerCase() === 'elite')) {
          // filter only Elite proxy
          anonymities = anonymities.filter((o) => o.anonymity.toLowerCase() === 'elite');
          protocols = anonymities.map((o) => o.protocol);
          anonymity = 'Elite';
        } else if (anonymities.some((o) => o.anonymity.toLocaleLowerCase() === 'anonymous')) {
          // filter only Anonymous proxy
          anonymities = anonymities.filter((o) => o.anonymity.toLowerCase() === 'anonymous');
          protocols = anonymities.map((o) => o.protocol);
          anonymity = 'Anonymous';
        }
      }
    }
    const error = Object.values(results)
      .map((crc) => crc.error)
      .filter((msg) => typeof msg === 'string' || msg instanceof Error);
    return { isValid, protocols, isSSL, anonymity, error };
  }
}
