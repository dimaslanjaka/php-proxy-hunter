import { afterAll, afterEach, beforeAll, describe, expect, test } from '@jest/globals';
import fs from 'fs';
import path from 'path';
import ProxyDB from '../src/ProxyDB.js';

// Use a temporary database for testing
const tempDbPath = path.join(process.cwd(), 'tmp', 'jest', 'test-database.sqlite');

let proxyDB;
const addedProxies = new Set();

/**
 * Adds a proxy to the database and tracks it for cleanup.
 * @param {string} proxy
 */
async function addAndTrack(proxy) {
  await proxyDB.add(proxy);
  addedProxies.add(proxy);
}

describe('ProxyDB', () => {
  beforeAll(async () => {
    proxyDB = new ProxyDB(tempDbPath, true);
    await proxyDB.startConnection(); // Ensure schema is created
  });

  afterEach(async () => {
    // Remove all proxies added in the test using proxyDB.remove
    for (const proxy of addedProxies) {
      await proxyDB.remove(proxy);
    }
    addedProxies.clear();
  });

  afterAll(async () => {
    if (proxyDB) {
      await proxyDB.close();
    }
    // Do not remove error log file
  });

  test('should initialize with default dbLocation', () => {
    const db = new ProxyDB();
    expect(db.dbLocation).toBe(path.join(process.cwd(), 'src', 'database.sqlite'));
  });

  test('should add and select a new proxy', async () => {
    const proxy = '1.2.3.4:8080';
    await addAndTrack(proxy);
    const result = await proxyDB.select(proxy);
    expect(result.length).toBe(1);
    expect(result[0].proxy).toBe(proxy);
  });

  test('should not add a proxy if it already exists', async () => {
    const proxy = '1.2.3.4:8080';
    await addAndTrack(proxy);
    await addAndTrack(proxy);
    const result = await proxyDB.select(proxy);
    expect(result.length).toBe(1);
  });

  test('should remove a proxy', async () => {
    const proxy = '5.6.7.8:3128';
    await addAndTrack(proxy);
    await proxyDB.remove(proxy);
    const result = await proxyDB.select(proxy);
    expect(result.length).toBe(0);
  });

  test('should update data and set last_check if status is not untested', async () => {
    const proxy = '9.9.9.9:9999';
    await addAndTrack(proxy);
    await proxyDB.updateData(proxy, { status: 'active', type: 'http' });
    const result = await proxyDB.select(proxy);
    expect(result[0].status).toBe('active');
    expect(result[0].type).toBe('http');
    expect(result[0].last_check).toBeTruthy();
  });

  test('should clean type in cleanType()', () => {
    const item = { type: 'http--socks5-' };
    const cleaned = proxyDB.cleanType(item);
    expect(cleaned.type).toBe('http-socks5');
  });

  test('should fix empty single data', async () => {
    const proxy = '8.8.8.8:8888';
    await addAndTrack(proxy);
    const item = { proxy };
    const fixed = await proxyDB.fixEmptySingleData(item);
    expect(fixed.useragent).toBeTruthy();
    expect(fixed.webgl_vendor).toBeTruthy();
    expect(fixed.browser_vendor).toBeTruthy();
    expect(fixed.webgl_renderer).toBeTruthy();
  });

  test('should fix empty data for array', async () => {
    const proxy = '7.7.7.7:7777';
    await addAndTrack(proxy);
    const arr = [{ proxy }];
    const fixedArr = await proxyDB.fixEmptyData(arr);
    expect(fixedArr[0].useragent).toBeTruthy();
  });

  test('should get all proxies', async () => {
    await addAndTrack('1.1.1.1:1111');
    await addAndTrack('2.2.2.2:2222');
    const all = await proxyDB.getAllProxies();
    expect(all.length).toBeGreaterThanOrEqual(2);
  });

  test('should get working proxies', async () => {
    const proxy = '3.3.3.3:3333';
    await addAndTrack(proxy);
    await proxyDB.updateData(proxy, { status: 'active' });
    const working = await proxyDB.getWorkingProxies();
    expect(Array.isArray(working)).toBe(true);
    expect(working[0].status).toBe('active');
  });

  test('should get untested proxies', async () => {
    const proxy = '4.4.4.4:4444';
    await addAndTrack(proxy);
    await proxyDB.updateData(proxy, { status: 'untested' });
    const untested = await proxyDB.getUntestedProxies();
    expect(Array.isArray(untested)).toBe(true);
    expect(untested.some((p) => p.status === 'untested')).toBe(true);
  });

  test('should append error log', () => {
    const now = new Date().toISOString();
    const msg = `[${now}] Test error`;
    proxyDB.appendErrorLog(msg);
    const log = fs.readFileSync(proxyDB.errorFile, 'utf-8');
    expect(log.includes(msg)).toBe(true);
  });
});
