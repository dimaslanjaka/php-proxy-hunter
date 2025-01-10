import ansiColors from 'ansi-colors';
import { logProxy, markProxyAsDead } from '../../node_browser/logger.js';
import { db } from '../database.js';
import { splitArrayIntoChunks } from '../utils/array.js';
import { extractProxies } from './extractor.js';
import { ProxyChecker } from './ProxyChecker.js';

const proxies = extractProxies(`
177.234.194.226:999
`);

const chunks = splitArrayIntoChunks(proxies, 5);
const checker = new ProxyChecker();
checker.on('debug', console.log);
checker.on('error', console.log);
checker.on('ip', console.log);
checker.on('title', console.log);

(async () => {
  for (let i = 0; i < chunks.length; i++) {
    const inner_chunks = chunks[i].map(async (proxyItem) => {
      const { isValid, anonymity, isSSL, protocols } = await checker.checkProxyResult(proxyItem);
      const db_data = {
        status: isValid ? 'active' : 'dead',
        type: protocols.join('-'),
        https: isSSL ? 'true' : 'false',
        anonymity: anonymity || 'unknown'
      };
      await db.updateData(proxyItem, db_data); // Pass signal if updateData supports cancellation

      let message = ansiColors.redBright('dead');
      if (isValid) {
        message = `${ansiColors.greenBright('active')} ${isSSL ? ansiColors.greenBright('SSL') : ansiColors.yellowBright('non-SSL')} ${protocols}`;
      } else {
        markProxyAsDead(proxyItem);
      }
      logProxy(`${proxyItem} ${message}`);
    });
    await Promise.all(inner_chunks);
  }
})();
