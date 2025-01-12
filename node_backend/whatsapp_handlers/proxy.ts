// noinspection JSIgnoredPromiseFromCall

import fs from 'fs-extra';
import lodash from 'lodash';
import { fileURLToPath } from 'url';
import { getFromProject } from '../../.env.mjs';
import ProxyDB from '../../src/ProxyDB.js';
import { getWhatsappFile, whatsappLogger } from '../Function.js';
import Replier from '../Replier.js';

const db = new ProxyDB(getWhatsappFile('src/database.sqlite'));
const __filename = fileURLToPath(import.meta.url);

// noinspection JSUnusedGlobalSymbols
export default async function proxyHandler(replier?: Replier) {
  const text = replier?.receivedText?.trim();
  if (
    text?.toLowerCase().includes('working proxy') ||
    text?.toLowerCase().includes('working proxies') ||
    text?.toLowerCase().startsWith('/working proxies')
  ) {
    await replier?.reply('Retrieving working proxies...');
    try {
      const proxies = await db.getWorkingProxies();
      const result = proxies.map((item) => {
        const mappedObj = Object.fromEntries(
          Object.entries(item).map(([key, value]) => [key, value == null ? 'unknown' : value])
        );
        return `${mappedObj.proxy} ${mappedObj.city} ${mappedObj.country} ${mappedObj.latency}ms`;
      });
      await replier?.reply(lodash.shuffle(result).slice(0, 30).join('\n'));
    } catch (_error) {
      let workingFile = getFromProject('working.txt');
      if (!fs.existsSync(workingFile)) workingFile = getWhatsappFile('working.txt');
      if (fs.existsSync(workingFile)) {
        const proxies = fs.readFileSync(workingFile, 'utf-8');
        await replier?.reply(proxies.split(/\r?\n/).slice(0, 5).join('\n'));
      }
    }
  } else if (text?.startsWith('/help')) {
    replier.reply(`Proxy Utility\n\n- \`/working proxies\` - get working proxies`);
  }
}

if (typeof require !== 'undefined' && require.main === module) {
  whatsappLogger.info(`${__filename} CJS called directly`);
  proxyHandler();
} else if (import.meta.url === new URL(import.meta.url).href && process.argv?.[1] === __filename) {
  whatsappLogger.info(`${__filename} ESM called directly`);
  proxyHandler();
} else {
  whatsappLogger.info(`${__filename} imported as a module`);
}
