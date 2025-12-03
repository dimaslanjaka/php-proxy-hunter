import fs from 'fs';
import path from 'path';
import { PROJECT_DIR } from './config.js';

/**
 * Common Windows Python locations to search for site-packages.
 * These entries may include a single wildcard segment (`*`) which will be
 * expanded by {@link expandWildcards} at runtime.
 * @type {string[]}
 */
const commonPythonPaths = [
  path.join(process.env.LOCALAPPDATA || '', 'Programs', 'Python', '*', 'Lib', 'site-packages'),
  path.join(process.env.APPDATA || '', 'Python', '*', 'site-packages'),
  'C:\\Python*\\Lib\\site-packages',
  'C:\\Program Files\\Python*\\Lib\\site-packages',
  PROJECT_DIR
];

// Expand wildcard segments (supports '*' only) into actual existing paths.
/**
 * Expand a pattern that may include a single-segment wildcard `*` into
 * the list of matching paths on disk. If the pattern contains no wildcard
 * the original string is returned in an array.
 *
 * Example: `C:\\Python*\\Lib\\site-packages` -> `['C:\\Python39\\Lib\\site-packages', ...]`
 *
 * @param {string} pattern - Path pattern possibly containing `*`.
 * @returns {string[]} Array of expanded paths (may be empty).
 */
function expandWildcards(pattern) {
  if (!pattern.includes('*')) return [pattern];

  const segments = pattern.split(path.sep);

  /**
   * Recursively expand path segments. This inner helper walks the segments
   * and replaces any segment containing `*` with matching directory names
   * from the filesystem.
   *
   * @param {number} idx - Current index in `segments` being processed.
   * @param {string[]} builtSegments - Accumulated path segments.
   * @returns {string[]} Expanded paths under the current branch.
   */
  function expandSegments(idx, builtSegments) {
    if (idx === segments.length) return [path.join(...builtSegments)];

    const seg = segments[idx];
    if (!seg.includes('*')) {
      return expandSegments(idx + 1, [...builtSegments, seg]);
    }

    // Build prefix to list candidates from
    const prefix = builtSegments.length
      ? path.join(...builtSegments)
      : segments[0] && segments[0].endsWith(':')
        ? segments[0] + path.sep
        : process.cwd();

    let dirCandidates = [];
    try {
      dirCandidates = fs
        .readdirSync(prefix, { withFileTypes: true })
        .filter((d) => d.isDirectory())
        .map((d) => d.name);
    } catch (_e) {
      return []; // cannot read prefix, abort expansion
    }

    const segRegex = new RegExp(
      '^' +
        seg
          .split('*')
          .map((s) => s.replace(/[.+?^${}()|[\\]\\]/g, '\\$&'))
          .join('.*') +
        '$',
      'i'
    );

    const matches = dirCandidates.filter((name) => segRegex.test(name));
    const results = [];
    for (const m of matches) {
      results.push(...expandSegments(idx + 1, [...builtSegments, m]));
    }
    return results;
  }

  return expandSegments(0, []);
}

// Detect common virtualenv locations to prefer their site-packages
function detectVenvSitePackages() {
  const candidates = [];

  if (process.env.VIRTUAL_ENV) candidates.push(path.join(process.env.VIRTUAL_ENV, 'Lib', 'site-packages'));
  if (process.env.CONDA_PREFIX) candidates.push(path.join(process.env.CONDA_PREFIX, 'Lib', 'site-packages'));

  const localVenvNames = ['venv', '.venv', 'env', '.env'];
  for (const name of localVenvNames) {
    const p = path.join(PROJECT_DIR || process.cwd(), name, 'Lib', 'site-packages');
    candidates.push(p);
  }

  return candidates.filter((p) => fs.existsSync(p));
}

// Resolve candidate paths, expand wildcards and keep only existing directories
const expanded = [];
for (const p of commonPythonPaths) {
  if (!p || typeof p !== 'string') continue;
  const resolved = expandWildcards(p);
  for (const r of resolved) {
    try {
      if (fs.existsSync(r)) expanded.push(r);
    } catch (_e) {
      // ignore
    }
  }
}

const venvPaths = detectVenvSitePackages();

// Include existing PYTHONPATH entries (preserve user setting)
const originalPyPath = (process.env.PYTHONPATH || '').split(path.delimiter).filter(Boolean);

// Build final PYTHONPATH: prefer venv paths, then discovered site-packages, then project dir, then original
/** @type {string[]} */
const finalPaths = [];
for (const p of venvPaths) if (!finalPaths.includes(p)) finalPaths.push(p);
for (const p of expanded) if (!finalPaths.includes(p)) finalPaths.push(p);
if (PROJECT_DIR && !finalPaths.includes(PROJECT_DIR)) finalPaths.push(PROJECT_DIR);
for (const p of originalPyPath) if (!finalPaths.includes(p)) finalPaths.push(p);

const newPythonPathValue = finalPaths.join(path.delimiter);
export const PYTHONPATH = newPythonPathValue;

// For debugging: print the configured PYTHONPATH if this script is run directly

if (process.argv.some((arg) => arg.includes('pythonFinder.js'))) {
  console.log('Configured PYTHONPATH:', newPythonPathValue);
}
