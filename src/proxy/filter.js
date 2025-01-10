import Database from 'better-sqlite3';
import Bluebird from 'bluebird';
import fs from 'fs-extra';
import _ from 'lodash';
import { isPortOpen } from './utils.js';

const dbPaths = ['src/database.sqlite', 'tmp/database.sqlite'];
/**
 * @type {Database[]}
 */
const dbs = [];

// Check if the database files exist before creating database instances
dbPaths.forEach((path) => {
  if (fs.existsSync(path)) {
    dbs.push(new Database(path));
  } else {
    console.error(`Database file not found: ${path}`);
  }
});

fs.ensureDir('tmp/caches'); // Ensure the cache directory exists

/**
 * Fetch proxies with the same IP.
 * @param {Database} db - The database instance to use.
 * @param {string[]} status - List of statuses to filter.
 * @param {number} limit - Maximum number of results to return.
 * @returns {Object<string, string[]>} - A dictionary with IPs as keys and lists of proxies as values.
 */
function fetchProxiesSameIp(db, status = ['dead', 'port-closed', 'untested', ''], limit = Number.MAX_SAFE_INTEGER) {
  // Create a condition string from the status list
  const condition = status.map((_s) => `status = ?`).join(' OR ');

  // Define the query to find proxies with the same IP but different ports
  const query = `
      SELECT proxy
      FROM proxies
      WHERE SUBSTR(proxy, 1, INSTR(proxy, ':') - 1) IN (
          SELECT SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)
          FROM proxies
          WHERE (${condition}) OR status IS NULL
          GROUP BY SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)
          HAVING COUNT(*) > 1
      )
      ORDER BY SUBSTR(proxy, 1, INSTR(proxy, ':') - 1), RANDOM()
      LIMIT ?
  `;

  // Prepare and execute the query
  const stmt = db.prepare(query);
  const proxies = stmt.all(...[...status, limit]);

  // Process the results
  const result = {};
  proxies.forEach((row) => {
    const proxy = row.proxy;
    const ip = proxy.split(':')[0];
    if (!result[ip]) {
      result[ip] = [];
    }
    result[ip].push(proxy);
  });

  // Filtering dictionary to include only key-value pairs where the list has more than 2 items
  const filteredResult = Object.fromEntries(Object.entries(result).filter(([_, v]) => v.length > 2));

  // Shuffle the items in the filteredResult
  const items = Object.entries(filteredResult); // Convert to a list of tuples
  _.shuffle(items); // Shuffle the list of tuples

  // Reconstruct the dictionary with shuffled items
  const shuffledResult = Object.fromEntries(items);

  return shuffledResult;
}

function deleteProxy(proxy) {
  dbs.forEach((db) => {
    const deleteStmt = db.prepare(`DELETE FROM proxies WHERE proxy = ?;`);
    const result = deleteStmt.run(proxy);
    console.log(`${proxy} port closed: Deleted ${result.changes} row(s) ${db.name}`);
  });
}

function markAsPortOpen(proxy) {
  dbs.forEach((db) => {
    const updateStmt = db.prepare(`UPDATE proxies SET status = 'port-open' WHERE proxy = ?;`);
    const result = updateStmt.run(proxy);
    console.log(`${proxy} port open: Updated ${result.changes} row(s) ${db.name}`);
  });
}

function markAsDead(proxy) {
  dbs.forEach((db) => {
    const updateStmt = db.prepare(`UPDATE proxies SET status = 'dead' WHERE proxy = ?;`);
    const result = updateStmt.run(proxy);
    console.log(`${proxy} dead: Updated ${result.changes} row(s) ${db.name}`);
  });
}

export async function mainFilter() {
  const ips = {};

  // Iterate over each database and fetch proxies
  dbs.forEach((db) => {
    const dbIps = fetchProxiesSameIp(db);
    for (const [key, value] of Object.entries(dbIps)) {
      if (ips[key]) {
        ips[key] = [...new Set([...ips[key], ...value])]; // Avoid duplicates
      } else {
        ips[key] = value;
      }
    }
  });

  for (const key in ips) {
    if (Object.hasOwnProperty.call(ips, key)) {
      const proxies = ips[key].map(async (proxy) => {
        return { open: await isPortOpen(proxy), proxy };
      });
      await Bluebird.all(proxies).then((results) => {
        // console.log(results);
        const isAllClosed = results.every((o) => !o.open);
        const isAllOpen = results.every((o) => o.open);
        // if all closed, delete all except first one
        if (isAllClosed) {
          markAsDead(results[0].proxy); // Handle the first item
          const allExceptFirst = results.slice(1); // Get all items except the first
          allExceptFirst.forEach((o) => deleteProxy(o.proxy)); // Process the rest
        } else if (isAllOpen) {
          // all proxy open, mark them as port-open
          results.forEach((o) => markAsPortOpen(o.proxy));
        } else {
          // not all proxy port closed
          const onlyClosed = results.filter((o) => !o.open);
          // delete only closed port
          onlyClosed.forEach((o) => deleteProxy(o.proxy));
          const onlyOpen = results.filter((o) => o.open);
          onlyOpen.forEach((o) => markAsPortOpen(o.proxy));
        }
      });
    }
  }
}
