import fs from 'fs-extra';
import path from 'upath';
import { fileURLToPath } from 'url';
import { spawn } from 'child_process';
import routes from '../src/react/routes.json' with { type: 'json' };

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const routesPath = path.join(__dirname, '..', 'src', 'react', 'routes.json');

/**
 * Normalize a route `path` value to a string for comparison.
 * Accepts a route object with a `path` key, a string, or an array of strings.
 * @param {{path?: string|string[]}|string|string[]|null|undefined} p - route object or path value
 * @returns {string} Normalized string suitable for `localeCompare`.
 */
function normalizePath(p) {
  if (p && typeof p === 'object' && 'path' in p) p = p.path;
  if (Array.isArray(p)) return p.join(' ');
  if (p === null || p === undefined) return '';
  return String(p);
}

/**
 * Compare two route objects (or path values) by their `path` key.
 * @param {{path?: string|string[]}|string|string[]|null|undefined} a - first route or path
 * @param {{path?: string|string[]}|string|string[]|null|undefined} b - second route or path
 * @returns {number} negative if `a < b`, zero if equal, positive if `a > b`.
 */
function compareByPath(a, b) {
  const pa = normalizePath(a && typeof a === 'object' && 'path' in a ? a.path : a);
  const pb = normalizePath(b && typeof b === 'object' && 'path' in b ? b.path : b);
  return pa.localeCompare(pb, undefined, { sensitivity: 'base', numeric: true });
}

/**
 * Sort an array of route entries by their `path` key, persist the sorted
 * array to `routes.json`, and run Prettier to format the file. Exits the
 * process with a non-zero code on error.
 *
 * @param {Array<any>} routes - Routes array loaded from JSON
 * @returns {void}
 */
function sortRoutes(routes) {
  try {
    if (!Array.isArray(routes)) {
      console.error('Error: routes.json does not contain an array at top level.');
      process.exit(2);
    }

    const sorted = routes.slice().sort(compareByPath);

    fs.writeFileSync(routesPath, JSON.stringify(sorted, null, 2) + '\n', 'utf8');

    console.log('routes.json sorted and saved:', routesPath);

    // Spawn Prettier to format the file we just wrote. Use npx executable
    // name compatible with Windows (npx.cmd) and POSIX (npx).
    const npxCmd = process.platform === 'win32' ? 'npx.cmd' : 'npx';
    const prettierArgs = ['prettier', '--write', routesPath];
    const child = spawn(npxCmd, prettierArgs, { stdio: 'inherit', shell: true });

    child.on('error', (e) => {
      console.error('Failed to spawn Prettier:', e && e.message ? e.message : e);
      process.exit(1);
    });

    child.on('close', (code) => {
      if (code === 0) {
        console.log('Prettier finished formatting', routesPath);
        process.exit(0);
      }
      console.error('Prettier exited with code', code);
      process.exit(code || 1);
    });
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err);
    console.error('Failed to sort routes.json:', msg);
    process.exit(1);
  }
}

/**
 * Remove duplicate route entries by normalized `path` value, keeping the
 * first occurrence of each unique path.
 *
 * @param {Array<object|string>} routes - Array of route objects or path-like values
 * @returns {Array<object|string>} Deduplicated array preserving original order
 */
function dedupeRoutes(routes) {
  const seen = new Set();
  const out = [];
  for (const r of routes) {
    const key = normalizePath(r && typeof r === 'object' && 'path' in r ? r.path : r);
    if (seen.has(key)) continue;
    seen.add(key);
    out.push(r);
  }
  return out;
}

/**
 * Checkout a single file from a git branch into the working tree and
 * invoke the callback with the parsed JSON content of that file.
 *
 * @param {string} branch - The branch name to checkout from (e.g., 'master').
 * @param {(routes: any[]) => void} callback - Called with the file contents parsed as JSON on success.
 * @returns {void}
 */
function checkout(branch, callback) {
  const gitCmd = process.platform === 'win32' ? 'git.exe' : 'git';
  const child = spawn(gitCmd, ['checkout', branch, routesPath], { stdio: 'inherit', shell: true });

  child.on('error', (e) => {
    console.error(`Failed to checkout branch ${branch}:`, e && e.message ? e.message : e);
    process.exit(1);
  });

  child.on('close', (code) => {
    if (code === 0) {
      console.log(`Checked out branch ${branch}`);
      callback(fs.readJSONSync(routesPath));
    } else {
      console.error(`Git checkout exited with code ${code} when checking out branch ${branch}`);
      process.exit(code || 1);
    }
  });
}

checkout('master', (masterRoutes) => {
  const allRoutes = [...masterRoutes, ...routes];
  checkout('python', (pythonRoutes) => {
    const merged = [...allRoutes, ...pythonRoutes];
    const unique = dedupeRoutes(merged);
    sortRoutes(unique);
  });
});
