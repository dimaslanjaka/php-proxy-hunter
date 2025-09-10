import mariadb from 'mariadb';
import moment from 'moment';
import { translate } from '@vitalets/google-translate-api';
import axios from 'axios';
import dotenv from 'dotenv';
import fs from 'fs';
import path from 'path';
import { HttpProxyAgent } from 'http-proxy-agent';
import proxyGrabber from 'proxies-grabber';
import { SocksProxyAgent } from 'socks-proxy-agent';
import source from './locales/id.json' with { type: 'json' };
import { fileURLToPath } from 'url';
import { array_shuffle } from '../utils/array';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Helper to get a MariaDB connection and ensure schema is applied
async function getConnection() {
  const conn = await mariadb.createConnection({
    host: process.env.MYSQL_HOST,
    user: process.env.MYSQL_USER,
    password: process.env.MYSQL_PASS,
    database: process.env.MYSQL_DBNAME,
    port: process.env.MYSQL_PORT ? Number(process.env.MYSQL_PORT) : 3306,
    allowPublicKeyRetrieval: true
  });
  // Read and execute schema
  const schemaPath = path.resolve(__dirname, '../PhpProxyHunter/assets/mysql-schema.sql');
  const schemaSql = fs.readFileSync(schemaPath, 'utf8');
  // Split on semicolon, filter out empty statements
  const statements = schemaSql
    .split(';')
    .map((s) => s.trim())
    .filter(Boolean);
  for (const stmt of statements) {
    await conn.query(stmt);
  }
  return conn;
}

// Check if proxy was processed in the last N hours
async function isProxyRecentlyProcessed(proxy: string, hours = 24): Promise<boolean> {
  const connection = await getConnection();
  try {
    const rows = await connection.query('SELECT updated FROM processed_proxies WHERE proxy = ?', [proxy]);
    if (rows.length > 0) {
      const lastUpdated = moment(rows[0].updated);
      if (moment().diff(lastUpdated, 'hours') < hours) {
        return true; // Already processed recently
      }
    }
    return false;
  } finally {
    await connection.end();
  }
}

// Save or update processed proxy with current timestamp
async function saveProcessedProxy(proxy: string) {
  const connection = await getConnection();
  try {
    // RFC3339 format: 2005-08-15T15:52:01+00:00
    const now = moment().format('YYYY-MM-DD[T]HH:mm:ssZ');
    await connection.query(
      'INSERT INTO processed_proxies (proxy, updated) VALUES (?, ?) ON DUPLICATE KEY UPDATE updated = ?',
      [proxy, now, now]
    );
    console.log(`[DB] Processed proxy updated: ${proxy}`);
  } catch (err) {
    console.error('[DB] Error updating processed proxy:', err);
  } finally {
    await connection.end();
  }
}

async function saveProxyToDB(proxy: string, status: string = 'working') {
  const connection = await getConnection();
  try {
    await connection.query('INSERT INTO proxies (proxy, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = ?', [
      proxy,
      status,
      status
    ]);
    console.log(`[DB] Proxy ${proxy} saved/updated as ${status}`);
  } catch (err) {
    console.error('[DB] Error saving proxy:', err);
  } finally {
    await connection.end();
  }
}

async function _run() {
  const toLangs = ['en'];
  for (const toLang of toLangs) {
    const buildObject = {} as Record<string, string>;
    for (const key in source) {
      const srcText = (source as any)[key];
      const res = await translate(srcText, { to: 'en' });
      console.log(`Translated "${srcText}" to "${res.text}"`);
      buildObject[key] = res.text;
    }
    const outputPath = `./locales/${toLang}.json`;
    fs.writeFileSync(outputPath, JSON.stringify(buildObject, null, 2));
    console.log(`Translation for ${toLang} saved to ${outputPath}`);
  }
}

async function checkProxy(proxy: string) {
  const protocols = ['http://', 'https://', 'socks4://', 'socks5://'];
  for (const protocol of protocols) {
    let agent;
    if (protocol === 'socks4://' || protocol === 'socks5://') {
      agent = new SocksProxyAgent(protocol + proxy);
    } else {
      agent = new HttpProxyAgent(protocol + proxy);
    }

    try {
      console.log(`[checkProxy] Testing proxy: ${protocol + proxy}`);
      const response = await axios.get('https://httpbin.org/ip', {
        httpAgent: agent,
        httpsAgent: agent,
        timeout: 60 * 1000
      });

      console.log(`[checkProxy] SUCCESS: Proxy ${protocol + proxy} responded with:`, response.data);
      // Save to DB if working
      await saveProxyToDB(protocol + proxy, 'working');
      return true;
    } catch (err) {
      console.log(`[checkProxy] FAIL: Proxy ${protocol + proxy} error:`, (err as Error).message);
      // continue to next protocol instead of returning false immediately
    }
  }
  return false;
}

async function findWorkingProxy() {
  const grabber = new proxyGrabber();
  const proxies = array_shuffle(await grabber.get());
  for (const item of proxies) {
    if (typeof item.proxy === 'string') {
      if (await isProxyRecentlyProcessed(item.proxy, 24)) {
        console.log('[SKIP] Recently processed proxy:', item.proxy);
        continue;
      }
      const test = await checkProxy(item.proxy);
      await saveProcessedProxy(item.proxy);
      if (test) {
        console.log('Found working proxy:', item.proxy);
        return item;
      }
    }
  }
  console.log('No working proxy found.');
  return;
}

findWorkingProxy();
