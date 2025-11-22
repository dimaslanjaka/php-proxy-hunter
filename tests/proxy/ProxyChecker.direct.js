import ansiColors from 'ansi-colors';
import { db } from '../../src/database.js';
import { splitArrayIntoChunks } from '../../src/utils/array.js';
import { extractProxies } from '../../src/proxy/extractor.js';
import { ProxyChecker } from '../../src/proxy/ProxyChecker.js';
import { logProxy, markProxyAsDead } from '../../src/proxy/logger.js';

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
      const proxyString = proxyItem.proxy || proxyItem;
      const { isValid, anonymity, isSSL, protocols } = await checker.checkProxyResult(proxyString);
      const db_data = {
        status: isValid ? 'active' : 'dead',
        type: protocols.join('-'),
        https: isSSL ? 'true' : 'false',
        anonymity: anonymity || 'unknown'
      };
      await db.updateData(proxyString, db_data); // Pass signal if updateData supports cancellation

      let message = ansiColors.redBright('dead');
      if (isValid) {
        message = `${ansiColors.greenBright('active')} ${isSSL ? ansiColors.greenBright('SSL') : ansiColors.yellowBright('non-SSL')} ${protocols}`;
      } else {
        markProxyAsDead(proxyString);
      }
      logProxy(`${proxyString} ${message}`);
    });
    await Promise.all(inner_chunks);
  }
})();
