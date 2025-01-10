import ansiColors from 'ansi-colors';
import fs from 'fs-extra';
import _ from 'lodash';
import path from 'path';
import { TypedEmitter } from 'tiny-typed-emitter';
import { logProxy } from '../../node_browser/logger.js';
import { toMs } from '../../node_browser/puppeteer/time_utils.js';
import { db } from '../database.js';
import { splitArrayIntoChunks } from '../utils/array.js';
import { removeStringsFromFile } from '../utils/file.js';
import { extractIps, extractProxies } from './extractor.js';
import { ProxyChecker } from './ProxyChecker.js';
import { isPortOpen, isValidProxy } from './utils.js';

/**
 * Generates a list of IP:PORT combinations starting from the specified IP and
 * varying the port from 80 to the maximum valid port number (65535).
 *
 * @param {string} ip - The IP address to use for the combinations.
 * @returns {string[]} An array of IP:PORT combinations.
 *
 * @example
 * const ipPortList = genPorts('192.168.1.1');
 * console.log(ipPortList); // ['192.168.1.1:80', '192.168.1.1:81', ...]
 */
export function genPorts(ip) {
  const file = 'tmp/ip-ports/' + ip + '.txt';
  fs.ensureDir(path.dirname(file));
  if (fs.existsSync(file))
    return fs
      .readFileSync(file, 'utf-8')
      .split(/\r?\n/)
      .filter((proxy) => isValidProxy(proxy));
  const minPort = 80;
  const maxPort = 65535;
  const ipPortList = [];

  for (let port = minPort; port <= maxPort; port++) {
    ipPortList.push(`${ip}:${port}`);
  }

  fs.writeFile(file, ipPortList.join('\n'));

  return ipPortList;
}

/**
 * @typedef {Object} ProxyHunterEvents
 * @property {(message: string, colorMessage: string) => void} log - Log events from the proxy hunter.
 * @property {(proxy: string) => void} port-open - Emitted when a port is open.
 * @property {(proxy: string) => void} port-closed - Emitted when a port is closed or proxy is dead.
 * @property {(error: Error|string) => void} error - Emitted when an error occurs.
 */

/**
 * Proxy Hunter Class
 * @extends {TypedEmitter<ProxyHunterEvents>}
 */
export class ProxyHunter extends TypedEmitter {
  /**
   * Constructor
   * @param {import("sbg-utility/dist/globals.js").Nullable<string>} data - The raw data to extract proxies from.
   */
  constructor(data) {
    super();
    this.data = data;
  }

  /**
   * Runs the proxy hunter.
   * @param {"port"|"proxy"} mode - Mode of operation. "port" checks if the port is open, "proxy" checks if the proxy is active.
   */
  async run(mode = 'port') {
    const ips = extractIps(this.data);
    const pairs = _.shuffle(
      ips.map((ip) => ({
        ip,
        pairs: genPorts(ip)
      }))
    );
    const checker = new ProxyChecker();
    checker.on('debug', logProxy);
    checker.on('error', logProxy);
    checker.on('ip', logProxy);
    checker.on('title', logProxy);

    for (const obj of pairs) {
      const file = `tmp/ip-ports/${obj.ip}.txt`;
      const chunks = _.shuffle(splitArrayIntoChunks(_.shuffle(obj.pairs), 5));

      for (const chunk of chunks) {
        const dataToRemove = [];
        const checks = chunk.map(async (proxy) => {
          try {
            if (mode === 'port') {
              if (!(await isPortOpen(proxy, toMs(60)))) {
                this.emit('log', `${proxy} ${ansiColors.redBright('port closed')}`);
                this.emit('port-closed', proxy);
                dataToRemove.push(proxy);
              } else {
                this.emit('log', `${proxy} ${ansiColors.greenBright('port open')}`);
                this.emit('port-open', proxy);
                db.updateData(proxy, { status: 'port-open' });
              }
            } else if (mode === 'proxy') {
              const results = await checker.checkProxy(proxy);
              const onlyActive = Object.fromEntries(
                Object.entries(results).filter(([_key, value]) => value.result === true)
              );
              let isValid = Object.keys(onlyActive).length > 0;
              let protocols = Object.keys(onlyActive).join('-').toLowerCase();

              // Try to find only HTTPS
              const onlyHttps = Object.fromEntries(
                Object.entries(results).filter(([_key, value]) => value.result === true && value.https === true)
              );
              const isSSL = Object.keys(onlyHttps).length > 0;
              if (isSSL) {
                isValid = Object.keys(onlyHttps).length > 0;
                protocols = Object.keys(onlyHttps).join('-').toLowerCase();
              }

              const db_data = { status: isValid ? 'active' : 'dead', type: protocols, https: isSSL ? 'true' : 'false' };
              if (isValid) {
                // only working proxy to update database
                db.updateData(proxy, db_data);
                this.emit('port-open', proxy);
              } else {
                this.emit('port-closed', proxy);
                dataToRemove.push(proxy);
              }
              this.emit(
                'log',
                proxy,
                isValid ? ansiColors.greenBright('active') : ansiColors.redBright('dead'),
                protocols,
                isSSL ? ansiColors.green('SSL') : ansiColors.yellow('non-SSL')
              );
            }
          } catch (error) {
            this.emit('error', `failed check ${proxy}: ${error.message}`);
          }
        });

        await Promise.all(checks);
        await removeStringsFromFile(file, dataToRemove);
      }

      const read = await fs.readFile(file, 'utf-8');
      const extract = extractProxies(read);
      await fs.writeFile(file, extract.join('\n'));
    }
  }
}
