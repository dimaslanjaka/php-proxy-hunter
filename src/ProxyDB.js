import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { randomWebGLData } from '../data/webgl.js';
import { getNuitkaFile, getRelativePath, readFile } from './func.js';
import { getCurrentRFC3339Time } from './func_date.js';
import { randomWindowsUA } from './func_useragent.js';
import SQLiteHelper from './SQLiteHelper.js';

// Get the current directory equivalent of __dirname in ESM
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// To work with electron
// electron-rebuild -f -w sqlite3

class ProxyDB {
  /**
   * Proxy database class.
   * @param {Partial<string>} dbLocation
   * @param {boolean} start
   */
  constructor(dbLocation = null, start = false) {
    this.dbLocation = dbLocation || getRelativePath('src/database.sqlite');
    this.db = null;

    if (start) {
      this.startConnection();
    }
  }

  async startConnection() {
    try {
      this.db = new SQLiteHelper(this.dbLocation);

      // Load SQL file to create tables
      const dbCreateFile = getNuitkaFile('assets/database/create.sql');
      const contents = await readFile(dbCreateFile, 'utf-8');
      const commands = contents
        .split(';')
        .map((cmd) => cmd.trim())
        .filter((cmd) => cmd);

      // Execute each SQL command
      for (const command of commands) {
        this.db.executeQuery(command);
      }

      const walEnabled = await this.getMetaValue('wal_enabled');
      if (!walEnabled) {
        this.db.executeQuery('PRAGMA journal_mode = WAL');
        await this.setMetaValue('wal_enabled', '1');
      }

      const autoVacuumEnabled = await this.getMetaValue('auto_vacuum_enabled');
      if (!autoVacuumEnabled) {
        this.db.executeQuery('PRAGMA auto_vacuum = FULL');
        await this.setMetaValue('auto_vacuum_enabled', '1');
      }

      await this.runDailyVacuum();
    } catch (error) {
      console.error(error);
      await this.appendErrorLog(error);
    }
  }

  async getMetaValue(key) {
    if (!this.db) await this.startConnection();
    const result = this.db.select('meta', 'value', 'key = ?', [key]);
    return result.length ? result[0].value : null;
  }

  async setMetaValue(key, value) {
    if (!this.db) await this.startConnection();
    this.db.executeQuery(`REPLACE INTO meta (key, value) VALUES (?, ?)`, [key, value]);
  }

  async runDailyVacuum() {
    const lastVacuumTime = await this.getMetaValue('last_vacuum_time');
    const currentTime = Math.floor(Date.now() / 1000);
    const oneDayInSeconds = 86400;

    if (!lastVacuumTime || currentTime - parseInt(lastVacuumTime) > oneDayInSeconds) {
      this.db.executeQuery('VACUUM');
      await this.setMetaValue('last_vacuum_time', currentTime.toString());
    }
  }

  async appendErrorLog(error) {
    const errorFile = getNuitkaFile('error.txt');
    fs.appendFile(errorFile, `${error}\n`);
  }

  cleanType(item) {
    if (item.type) {
      const types = item.type.split('-').filter((t) => t);
      item.type = types.join('-');
    }
    return item;
  }

  /**
   * fix proxy object empty data
   * @param {Record<string, any>} item
   * @returns
   */
  async fixEmptySingleData(item) {
    let modify = false;
    let dbData = { ...item };

    // if (!item.country || !item.timezone || !item.longitude || !item.latitude) {
    //   const geo = await getGeoIp2(item.proxy);
    //   if (geo) {
    //     modify = true;
    //     dbData = { ...dbData, ...geo };
    //   }
    // }

    if (!item.webgl_renderer || !item.webgl_vendor || !item.browser_vendor) {
      modify = true;
      const webgl = randomWebGLData();
      dbData.webgl_vendor = webgl.webgl_vendor;
      dbData.browser_vendor = webgl.browser_vendor;
      dbData.webgl_renderer = webgl.webgl_renderer;
    }

    if (!item.useragent) {
      modify = true;
      dbData.useragent = randomWindowsUA();
    }

    dbData = this.cleanType(dbData);

    if (modify) {
      await this.updateData(item.proxy, dbData);
    }

    return dbData;
  }

  /**
   * fix array of proxy object empty data
   * @param {Array<Record<string, any>>} results
   * @returns
   */
  async fixEmptyData(results) {
    if (!results || !results.length) return [];
    return Promise.all(results.map((result) => this.fixEmptySingleData(result)));
  }

  async add(proxy) {
    const sel = await this.select(proxy);
    if (sel.length === 0) {
      this.db.insert('proxies', { proxy: proxy.trim() });
    } else {
      console.log(`Proxy ${proxy} already exists`);
    }
    return this.select(proxy);
  }

  async select(proxy) {
    if (!this.db) await this.startConnection();
    return this.db.select('proxies', '*', 'proxy = ?', [proxy.trim()]);
  }

  /**
   * Retrieves all proxies from the database with optional random ordering and limiting.
   * @param {boolean} [rand=false] - Whether to order the results randomly (default is false).
   * @param {number} [limit=Number.MAX_VALUE] - The maximum number of rows to return (default is Number.MAX_VALUE).
   * @returns {Promise<Array<Record<string, any>>>} - A promise that resolves to a list of proxies, where each proxy is represented as an object with column names as keys.
   */
  async getAllProxies(rand = false, limit = Number.MAX_VALUE) {
    if (!this.db) await this.startConnection();
    return this.db.select('proxies', '*', '', [], rand, limit);
  }

  async remove(proxy) {
    if (!this.db) await this.startConnection();
    this.db.delete('proxies', 'proxy = ?', [proxy.trim()]);
  }

  async updateData(proxy, data = {}) {
    if (!proxy.trim()) return;
    const select = await this.select(proxy);
    if (select.length === 0) {
      await this.add(proxy);
    }

    if ('status' in data && data.status !== 'untested') {
      data.last_check = getCurrentRFC3339Time();
    }

    data = this.cleanType(data);
    this.db.update('proxies', data, 'proxy = ?', [proxy.trim()]);
  }

  /**
   * get working proxies
   * @param {boolean} autoFix
   * @returns {Promise<Record<string, any>[]>}
   */
  async getWorkingProxies(autoFix = true, rand = true, limit = 1000) {
    if (!this.db) await this.startConnection();
    const result = this.db.select('proxies', '*', 'status = ?', ['active'], rand, limit);
    return autoFix ? await this.fixEmptyData(result) : result;
  }

  /**
   * Get untested proxies
   * @returns {Promise<Record<string, any>[]>}
   */
  async getUntestedProxies(rand = true, limit = 1000) {
    if (!this.db) await this.startConnection();
    const result = this.db.select('proxies', '*', 'status = ?', ['untested'], rand, limit);
    const result2 = this.db.select('proxies', '*', 'status = ? OR status IS NULL OR status = ?', ['untested', '']);
    return result.concat(result2);
  }
}
export default ProxyDB;
