import fs from 'fs';
import path from 'path';

const EXCLUDES = new Set(['.vite', '.cache', '.dump', '.bin', '.vite-temp']);
const rootArg = process.argv[2] || 'node_modules';
const root = path.resolve(process.cwd(), rootArg);

/**
 * List top-level folders inside a `node_modules` directory.
 *
 * - Skips entries listed in `EXCLUDES`.
 * - Expands scoped packages (e.g. `@scope/pkg`) and returns their child package paths.
 * - Returned paths are relative to `process.cwd()` and any leading `node_modules/` prefix is removed.
 *
 * @param {string} nodeModulesPath - Path to the `node_modules` directory to inspect.
 * @returns {string[]} Array of relative folder paths (no leading `node_modules/`).
 */
function listTopLevelFolders(nodeModulesPath) {
  const results = [];
  try {
    const entries = fs.readdirSync(nodeModulesPath, { withFileTypes: true });
    for (const e of entries) {
      if (!e.isDirectory()) continue;
      if (EXCLUDES.has(e.name)) continue;

      const full = path.join(nodeModulesPath, e.name);
      if (e.name.startsWith('@')) {
        // scoped packages: list their children
        try {
          const scoped = fs.readdirSync(full, { withFileTypes: true });
          for (const s of scoped) {
            if (s.isDirectory() && !EXCLUDES.has(s.name)) {
              let rel = path.relative(process.cwd(), path.join(full, s.name));
              const prefix = 'node_modules' + path.sep;
              if (rel.startsWith(prefix)) rel = rel.slice(prefix.length);
              results.push(rel);
            }
          }
        } catch (_) {}
      } else {
        let rel = path.relative(process.cwd(), full);
        const prefix = 'node_modules' + path.sep;
        if (rel.startsWith(prefix)) rel = rel.slice(prefix.length);
        results.push(rel);
      }
    }
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err);
    console.error(`Failed to read ${nodeModulesPath}:`, msg);
    process.exitCode = 1;
  }
  return results;
}

const folders = listTopLevelFolders(root);

/**
 * Sanitize a value for use as a filename or chunk name.
 *
 * Removes common path prefixes (like `node_modules/`), normalizes
 * separators and scope markers, replaces disallowed characters with the
 * provided replacement, collapses repeated replacement characters, and
 * trims boundary characters.
 *
 * @param {string|any} input - Value to sanitize (will be converted to string).
 * @param {object} [options] - Options object.
 * @param {string} [options.replaceWith='_'] - Replacement for disallowed characters.
 * @param {number} [options.maxLength=200] - Maximum length of the returned string.
 * @returns {string} A sanitized filename (never empty; returns '_' when input is empty).
 */
export function sanitizeFilename(input, { replaceWith = '_', maxLength = 200 } = {}) {
  if (input == null) return '';
  let s = String(input).trim();
  if (!s) return '';

  // Remove common prefixes
  s = s.replace(new RegExp('^node_modules[\\/]', 'i'), '');

  // Normalize separators and scope markers to replacement
  s = s.replace(new RegExp('[\\/@:\\s]+', 'g'), replaceWith);

  // Remove any remaining disallowed characters (keep alnum, dot, underscore, dash)
  s = s.replace(/[^a-zA-Z0-9._-]/g, replaceWith);

  // Collapse multiple replacements into one
  const repEsc = replaceWith.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  s = s.replace(new RegExp(repEsc + '{2,}', 'g'), replaceWith);

  // Trim replacement characters and dots from ends
  s = s.replace(new RegExp('^' + repEsc + '+|' + repEsc + '+$', 'g'), '');
  s = s.replace(/^\.+|\.+$/g, '');

  if (maxLength && s.length > maxLength) s = s.slice(0, maxLength);
  return s || '_';
}

export { listTopLevelFolders, folders as node_modules_folders };
export default listTopLevelFolders;
