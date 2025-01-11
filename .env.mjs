#!/usr/bin/env node
import dotenv from 'dotenv';
import minimist from 'minimist';
import os from 'os';
import path from 'path';
import process, { env } from 'process';
import puppeteer from 'puppeteer';
import upath from 'upath';
import { fileURLToPath } from 'url';

// Setup `__dirname` and `__filename` equivalents for ESM
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Load environment variables
dotenv.config({ override: true, path: path.join(__dirname, '.env') });

// Argument parsing
const argv = minimist(process.argv.slice(2));
const is_debug = argv.debug || false;

// System info
const cpuCount = os.cpus().length;
const isGitHubCI = env.CI === 'true';

export { argv, cpuCount, is_debug, isGitHubCI };

// Electron and packaging checks
const isElectron = typeof process.versions.electron !== 'undefined';
const isPkg = typeof process.pkg !== 'undefined';
const assets = [
  'public/static/**/*',
  'src/database.*',
  'assets/**/*',
  'django_backend/certificates/**/*',
  './assets/chrome-extensions/**/*',
  './assets/database/**/*'
];
export { assets, isElectron };

// Chromium executable path handling
let chromiumExecutablePath = isPkg
  ? puppeteer
      .executablePath()
      .replace(/^.*?\/node_modules\/puppeteer\/\.local-chromium/, path.join(path.dirname(process.execPath), 'chromium'))
  : puppeteer.executablePath();

if (process.platform === 'win32') {
  chromiumExecutablePath = isPkg
    ? puppeteer
        .executablePath()
        .replace(
          /^.*?\\node_modules\\puppeteer\\\.local-chromium/,
          path.join(path.dirname(process.execPath), 'chromium')
        )
    : puppeteer.executablePath();
}

export { chromiumExecutablePath };

// GitHub token read-only
const githubTokenRO = process.env.GITHUB_TOKEN_READ_ONLY;
export { githubTokenRO };

/**
 * Determines whether debug mode is enabled based on environment variables and system hostname.
 * @returns {boolean} - True if in debug mode, false otherwise.
 */
const isDebug = (() => {
  const isGitHubCI = env.CI !== undefined && env.GITHUB_ACTIONS === 'true';
  const isGitHubCodespaces = env.CODESPACES === 'true';

  if (isGitHubCI || isGitHubCodespaces) return true;

  const debugPC = ['DESKTOP-JVTSJ6I'];
  const hostname = os.hostname();
  return hostname.startsWith('codespaces-') || debugPC.includes(hostname);
})();
export { isDebug };

/**
 * Resolves a file or folder path relative to the project directory.
 * @param {...string} paths - Path segments to resolve.
 * @returns {string} - Resolved absolute path.
 */
export function getFromProject(...paths) {
  const actual = path.join(__dirname, ...paths);

  if (os.platform() === 'win32') {
    const { root } = path.parse(actual);
    const dirWithoutRoot = actual.replace(root, '');
    return root.toUpperCase() + dirWithoutRoot;
  }
  return actual;
}

/**
 * Resolves a file or folder path relative to the project directory in UNIX format.
 * @param {...string} paths - Path segments to resolve.
 * @returns {string} - Resolved path in UNIX format.
 */
export function getFromProjectUnix(...paths) {
  return upath.join(__dirname, ...paths);
}

// Directories
const tmpDir = getFromProject('tmp');
const dataDir = getFromProject('data');
const srcDir = path.join('src');
export { dataDir, srcDir, tmpDir };

/**
 * Resolves a file or folder path near the executable location.
 * If running within Electron, considers whether the app is packaged.
 * @param {...string} paths - Path segments to resolve.
 * @returns {Promise<string>} - Resolved path.
 */
export async function getFromNearExe(...paths) {
  const electron = await import('electron').catch(() => ({}));

  if (electron.app) {
    const isPackaged = electron.app.isPackaged;
    if ((env.NODE_ENV === 'development' && !isPackaged) || !isPackaged) {
      return path.join(__dirname, ...paths);
    } else {
      return path.join(env.PORTABLE_EXECUTABLE_DIR, ...paths);
    }
  } else {
    return path.join(__dirname, ...paths);
  }
}
