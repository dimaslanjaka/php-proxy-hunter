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

try {
  // const raw = fs.readFileSync(routesPath, 'utf8');
  // const data = JSON.parse(raw);
  const data = routes;

  if (!Array.isArray(data)) {
    console.error('Error: routes.json does not contain an array at top level.');
    process.exit(2);
  }

  const sorted = data.slice().sort(compareByPath);

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
