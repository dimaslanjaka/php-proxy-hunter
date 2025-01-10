import fs from 'fs-extra';
import path from 'path';
import { db } from '../database.js';
import { isValidProxy } from './utils.js';

/**
 * ProxyWorkingManager class to manage proxies in a JSON file.
 */
class ProxyWorkingManager {
  constructor(filePath = path.join(process.cwd(), 'working.json')) {
    this.filePath = filePath;
    this.db = db;
  }

  /**
   * Get all proxy entries.
   * @returns {Promise<Array<{ proxy: string, type: string, status: string, [key: string]: any }>>} - The current list of proxies.
   */
  async getAll() {
    return await this._readFile();
  }

  /**
   * Add a new proxy entry.
   * @param {Object} newProxy - The new proxy entry to add.
   */
  async add(newProxy) {
    const proxies = await this._readFile();
    proxies.push(newProxy);
    await this._writeFile(proxies);
  }

  /**
   * Remove a proxy entry by its proxy value.
   * @param {string} proxyValue - The proxy value to remove.
   */
  async remove(proxyValue) {
    let proxies = await this._readFile();
    proxies = proxies.filter((proxy) => proxy.proxy !== proxyValue);
    await this._writeFile(proxies);
  }

  /**
   * Modify a proxy entry by its proxy value.
   * @param {string} proxyValue - The proxy value to modify.
   * @param {Object} updatedData - The data to update the proxy entry with.
   */
  async modify(proxyValue, updatedData) {
    let proxies = await this._readFile();
    proxies = proxies.map((proxy) => (proxy.proxy === proxyValue ? { ...proxy, ...updatedData } : proxy));
    await this._writeFile(proxies);
  }

  /**
   * Internal helper to read the JSON file.
   * @returns {Promise<Array<{ proxy: string, type: string, status: string, [key: string]: any }>>} - The current proxy list.
   * @private
   */
  async _readFile() {
    try {
      const data = await fs.readFile(this.filePath, 'utf-8');
      return JSON.parse(data);
    } catch (error) {
      console.error('Error reading file:', error);
      return [];
    }
  }

  /**
   * Internal helper to write to the JSON file.
   * @param {Array<{ proxy: string, type: string, status: string, [key: string]: any }>} data - The updated proxy list.
   * @private
   */
  async _writeFile(data) {
    try {
      await fs.writeFile(this.filePath, JSON.stringify(data, null, 2), 'utf-8');
    } catch (error) {
      console.error('Error writing to file:', error);
    }
  }

  /**
   * Write working.json from database working proxies
   */
  async writeWorkingProxiesFromDB() {
    if (!this.db.db) await this.db.startConnection();
    const data = await this.db.getWorkingProxies(false, false, 10000);
    const filteredData = [];
    for (let i = 0; i < data.length; i++) {
      const item = data[i];
      const proxyValid = item.proxy && typeof item.proxy === 'string' && isValidProxy(item.proxy);
      const statusActive = item.status === 'active';
      if (proxyValid && statusActive) {
        filteredData.push(item);
      }
    }
    this._writeFile(filteredData);
  }
}

export default ProxyWorkingManager;
