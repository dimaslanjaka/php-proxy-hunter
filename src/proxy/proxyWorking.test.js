import _ from 'lodash';
import { db } from '../database.js';
import { splitArrayIntoChunks } from '../utils/array.js';
import ProxyWorkingManager from './proxyWorking.js';

const mgr = new ProxyWorkingManager();
mgr.getAll().then(async (items) => {
  const chunks = splitArrayIntoChunks(items, 10);
  for (let i = 0; i < chunks.length; i++) {
    const element = chunks[i].map(async (item) => {
      const sel = await db.select(item.proxy);
      if (sel.length === 0) {
        console.log(item.proxy, 'not indexed');
        await db.add(item.proxy).catch(_.noop);
      }
      const fixItem = {};
      for (const key in item) {
        if (Object.hasOwnProperty.call(item, key)) {
          const value_2 = item[key];
          if (!['proxy', 'id'].includes(key) && value_2) {
            fixItem[key] = value_2;
          }
        }
      }
      await db.updateData(item.proxy, { status: 'active', ...fixItem });
      console.log(item.proxy, 'marked as active');
    });
    await Promise.all(element);
  }
});
