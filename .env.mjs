#!/usr/bin/env node
import dotenv from 'dotenv';
import os from 'os';
import path from 'path';
import { fileURLToPath } from 'url';
import process, { env } from 'process';
import puppeteer from 'puppeteer';
import upath from 'upath';
import minimist from 'minimist';

// Setup `__dirname` and `__filename` equivalents for ESM
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Load environment variables
dotenv.config({ override: true, path: __dirname });

// Argument parsing
const argv = minimist(process.argv.slice(2));
const is_debug = argv.debug || false;

// System info
const cpuCount = os.cpus().length;
const isGitHubCI = env.CI === 'true';

export { isGitHubCI, argv, is_debug, cpuCount };

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
export { isElectron, assets };

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

// Debug mode check
const isDebug = (() => {
  const isGitHubCI = env.CI !== undefined && env.GITHUB_ACTIONS === 'true';
  const isGitHubCodespaces = env.CODESPACES === 'true';

  if (isGitHubCI || isGitHubCodespaces) return true;

  const debugPC = ['DESKTOP-JVTSJ6I'];
  const hostname = os.hostname();
  return hostname.startsWith('codespaces-') || debugPC.includes(hostname);
})();
export { isDebug };

// Get file or folder from project directory
export function getFromProject(...paths) {
  const actual = path.join(__dirname, ...paths);

  if (os.platform() === 'win32') {
    const { root } = path.parse(actual);
    const dirWithoutRoot = actual.replace(root, '');
    return root.toUpperCase() + dirWithoutRoot;
  }
  return actual;
}

// Get file or folder from project directory in UNIX format
export function getFromProjectUnix(...paths) {
  return upath.join(__dirname, ...paths);
}

// Directories
const tmpDir = getFromProject('tmp');
const dataDir = getFromProject('data');
const srcDir = path.join('src');
export { tmpDir, dataDir, srcDir };

// Get file or folder from near exe location
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
