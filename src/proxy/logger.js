import ansi2html from 'ansi-to-html';
import Database from 'better-sqlite3';
import fs from 'fs-extra';
import path from 'path';
import { toHtmlEntities } from '../utils/string.js';
import { toMilliseconds } from 'sbg-utility';

const __dirname = path.dirname(new URL(import.meta.url).pathname);
const ansi2htmlConverter = new ansi2html();

// Function to create a write stream for the specified log file
const createLogStream = (filePath) => {
  // Ensure the directory exists
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  return fs.createWriteStream(filePath, { flags: 'a' });
};

// Define log file paths
export const logProxyFile = path.join(process.cwd(), 'tmp/logs/proxyChecker.txt');
export const logBrowserFile = path.join(process.cwd(), 'tmp/logs/browser.txt');
export const logErrorFile = path.join(process.cwd(), 'tmp/logs/error.txt');

[logProxyFile, logBrowserFile, logErrorFile].forEach((f) => {
  fs.ensureDirSync(path.dirname(f));
  fs.writeFileSync(f, 'Logger started at ' + new Date() + '\n');
});

// Create initial writable streams
let logProxyStream = createLogStream(logProxyFile);
let logBrowserStream = createLogStream(logBrowserFile);
let logErrorStream = createLogStream(logErrorFile);

// Function to check and recreate the stream if the file is deleted
const ensureStream = (stream, filePath) => {
  // Check if the file exists
  if (!fs.existsSync(filePath)) {
    // Recreate the stream if the file doesn't exist
    stream.end(); // Close the existing stream
    return createLogStream(filePath);
  }
  return stream; // Return the existing stream
};

// Queue for log operations
const logQueue = [];
let isProcessingLogQueue = false;

/**
 * Processes the log operations sequentially.
 * This function ensures that each log operation is handled one at a time,
 * preventing concurrent writes to the log streams.
 *
 * @returns {Promise<void>} - Resolves when all log operations are processed.
 */
async function processLogQueue() {
  if (isProcessingLogQueue || logQueue.length === 0) return;

  isProcessingLogQueue = true;
  const { type, args } = logQueue.shift(); // Get the next operation

  try {
    // Process the appropriate log type (error, proxy, or browser)
    switch (type) {
      case 'error':
        await processLog(logErrorStream, logProxyFile, args);
        break;
      case 'proxy':
        await processLog(logProxyStream, logProxyFile, args);
        break;
      case 'browser':
        await processLog(logBrowserStream, logBrowserFile, args);
        break;
    }
  } finally {
    isProcessingLogQueue = false;
    processLogQueue(); // Continue processing next logs in the queue
  }
}

/**
 * Handles the writing of log entries to a stream.
 * This function writes the given log arguments to the specified log stream
 * after converting them to HTML-safe entities.
 *
 * @param {WritableStream} stream - The log stream to write to.
 * @param {string} logFile - The file path to the log file.
 * @param {Array} args - The arguments to log.
 * @returns {Promise<void>} - Resolves after writing the log to the stream.
 */
async function processLog(stream, logFile, args) {
  console.log.apply(console, args);
  stream = ensureStream(stream, logFile); // Ensure the stream is valid
  stream.write(
    ansi2htmlConverter.toHtml(
      args
        .map((o) => {
          if (typeof o === 'string') return o;
          if (typeof o === 'object') return JSON.stringify(o);
          return new String(o);
        })
        .filter((s) => s.trim().length > 0)
        .map((s) => toHtmlEntities(s))
        .join(' ')
    ) + '\n'
  );
}

/**
 * Adds a log operation to the queue.
 * This function ensures that log operations are handled in a sequential manner.
 *
 * @param {string} type - The type of log (e.g., 'error', 'proxy', 'browser').
 * @param {Array} args - The arguments to log.
 */
function queueLog(type, args) {
  logQueue.push({ type, args });
  processLogQueue();
}

/**
 * Logs error messages to the error log stream.
 * This function overrides `console.log` for error logging and writes logs to the error log file.
 *
 * @param {...*} args - The arguments to log as error messages.
 */
export const logError = function (...args) {
  queueLog('error', args);
};

/**
 * Logs proxy-related messages to the proxy log stream.
 * This function overrides `console.log` for proxy logging and writes logs to the proxy log file.
 *
 * @param {...*} args - The arguments to log as proxy messages.
 */
export const logProxy = function (...args) {
  queueLog('proxy', args);
};

/**
 * Logs browser-related messages to the browser log stream.
 * This function overrides `console.log` for browser logging and writes logs to the browser log file.
 *
 * @param {...*} args - The arguments to log as browser messages.
 */
export const logBrowser = function (...args) {
  queueLog('browser', args);
};

export function cleanLogProxy() {
  fs.ensureDirSync(path.dirname(logProxyFile));
  fs.writeFileSync(logProxyFile, 'Logger reset at ' + new Date() + '\n');
}

export function cleanLogBrowser() {
  fs.ensureDirSync(path.dirname(logBrowserFile));
  fs.writeFileSync(logBrowserFile, 'Logger reset at ' + new Date() + '\n');
}

// Proxy (dead/used) Logger

const dbPath = path.join(process.cwd(), 'tmp/caches/cache.db');
fs.ensureDir(path.dirname(dbPath));
const db = new Database(dbPath);

// Use the traditional rollback journal mode to avoid WAL files.
db.pragma('journal_mode = DELETE');
// Enable Auto-Vacuum Mode
db.pragma('auto_vacuum = FULL');

// Ensure the tables for used and dead proxies exist, with a unique proxy column
db.exec(`
  CREATE TABLE IF NOT EXISTS used_proxies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    proxy TEXT NOT NULL UNIQUE,
    expiresAt INTEGER NOT NULL
  );

  CREATE TABLE IF NOT EXISTS dead_proxies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    proxy TEXT NOT NULL UNIQUE,
    expiresAt INTEGER NOT NULL
  );
`);

/**
 * Check if a proxy is in the dead cache
 * @param {string} proxy
 * @returns {boolean}
 */
export function isDeadProxyInCache(proxy) {
  const now = Date.now();
  const row = db.prepare(`SELECT * FROM dead_proxies WHERE proxy = ? AND expiresAt > ?`).get(proxy, now);

  return !!row; // Returns true if the row exists and is not expired
}

/**
 * Check if a proxy is in the used cache
 * @param {string} proxy
 * @returns {boolean}
 */
export function isUsedProxyInCache(proxy) {
  const now = Date.now();
  const row = db.prepare(`SELECT * FROM used_proxies WHERE proxy = ? AND expiresAt > ?`).get(proxy, now);

  return !!row; // Returns true if the row exists and is not expired
}

/**
 * Mark a proxy as used for 1 hour, cleaning expired entries before insertion
 * @param {string} proxy
 */
export function markProxyAsUsed(proxy) {
  const expiresAt = Date.now() + toMilliseconds(1); // [n] hour in milliseconds

  // Use INSERT OR REPLACE to handle uniqueness, replaces the entry if already exists
  db.prepare(`INSERT OR REPLACE INTO used_proxies (proxy, expiresAt) VALUES (?, ?)`).run(proxy, expiresAt);
}

/**
 * Mark a proxy as dead for 5 hours, cleaning expired entries before insertion
 * @param {string} proxy
 */
export function markProxyAsDead(proxy) {
  const expiresAt = Date.now() + toMilliseconds(5); // 5 hours in milliseconds

  // Use INSERT OR REPLACE to handle uniqueness, replaces the entry if already exists
  db.prepare(`INSERT OR REPLACE INTO dead_proxies (proxy, expiresAt) VALUES (?, ?)`).run(proxy, expiresAt);
}

/**
 * Remove expired entries from both used and dead proxy tables
 */
export function cleanExpired() {
  const now = Date.now();
  db.prepare(`DELETE FROM used_proxies WHERE expiresAt <= ?`).run(now);
  db.prepare(`DELETE FROM dead_proxies WHERE expiresAt <= ?`).run(now);
  db.exec('VACUUM');
}

/**
 * Remove all data from both used_proxies and dead_proxies tables
 */
export function clearAll() {
  // Delete all rows from both tables
  db.prepare(`DELETE FROM used_proxies`).run();
  db.prepare(`DELETE FROM dead_proxies`).run();

  db.exec('VACUUM'); // Clean up the database file size
}

cleanExpired(); // clean at first time run
setInterval(cleanExpired, toMilliseconds(0, 30)); // Clean every 30 minutes
