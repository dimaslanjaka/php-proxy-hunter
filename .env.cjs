#!/usr/bin/env node
'use strict';

var dotenv = require('dotenv');
var os = require('os');
var path = require('path');
var url = require('url');
var process = require('process');
var puppeteer = require('puppeteer');
var upath = require('upath');
var minimist = require('minimist');

var _documentCurrentScript = typeof document !== 'undefined' ? document.currentScript : null;
// Setup `__dirname` and `__filename` equivalents for ESM
const __filename$1 = url.fileURLToPath(
  typeof document === 'undefined'
    ? require('u' + 'rl').pathToFileURL(__filename).href
    : (_documentCurrentScript &&
        _documentCurrentScript.tagName.toUpperCase() === 'SCRIPT' &&
        _documentCurrentScript.src) ||
        new URL('.env.cjs', document.baseURI).href
);
const __dirname$1 = path.dirname(__filename$1);

// Load environment variables
dotenv.config({ override: true, path: __dirname$1 });

// Argument parsing
const argv = minimist(process.argv.slice(2));
const is_debug = argv.debug || false;

// System info
const cpuCount = os.cpus().length;
const isGitHubCI = process.env.CI === 'true';

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

// Chromium executable path handling
exports.chromiumExecutablePath = isPkg
  ? puppeteer
      .executablePath()
      .replace(/^.*?\/node_modules\/puppeteer\/\.local-chromium/, path.join(path.dirname(process.execPath), 'chromium'))
  : puppeteer.executablePath();

if (process.platform === 'win32') {
  exports.chromiumExecutablePath = isPkg
    ? puppeteer
        .executablePath()
        .replace(
          /^.*?\\node_modules\\puppeteer\\\.local-chromium/,
          path.join(path.dirname(process.execPath), 'chromium')
        )
    : puppeteer.executablePath();
}

// GitHub token read-only
const githubTokenRO = process.env.GITHUB_TOKEN_READ_ONLY;

// Debug mode check
const isDebug = (() => {
  const isGitHubCI = process.env.CI !== undefined && process.env.GITHUB_ACTIONS === 'true';
  const isGitHubCodespaces = process.env.CODESPACES === 'true';

  if (isGitHubCI || isGitHubCodespaces) return true;

  const debugPC = ['DESKTOP-JVTSJ6I'];
  const hostname = os.hostname();
  return hostname.startsWith('codespaces-') || debugPC.includes(hostname);
})();

// Get file or folder from project directory
function getFromProject(...paths) {
  const actual = path.join(__dirname$1, ...paths);

  if (os.platform() === 'win32') {
    const { root } = path.parse(actual);
    const dirWithoutRoot = actual.replace(root, '');
    return root.toUpperCase() + dirWithoutRoot;
  }
  return actual;
}

// Get file or folder from project directory in UNIX format
function getFromProjectUnix(...paths) {
  return upath.join(__dirname$1, ...paths);
}

// Directories
const tmpDir = getFromProject('tmp');
const dataDir = getFromProject('data');
const srcDir = path.join('src');

// Get file or folder from near exe location
async function getFromNearExe(...paths) {
  const electron = await import('electron').catch(() => ({}));

  if (electron.app) {
    const isPackaged = electron.app.isPackaged;
    if ((process.env.NODE_ENV === 'development' && !isPackaged) || !isPackaged) {
      return path.join(__dirname$1, ...paths);
    } else {
      return path.join(process.env.PORTABLE_EXECUTABLE_DIR, ...paths);
    }
  } else {
    return path.join(__dirname$1, ...paths);
  }
}

exports.argv = argv;
exports.assets = assets;
exports.cpuCount = cpuCount;
exports.dataDir = dataDir;
exports.getFromNearExe = getFromNearExe;
exports.getFromProject = getFromProject;
exports.getFromProjectUnix = getFromProjectUnix;
exports.githubTokenRO = githubTokenRO;
exports.isDebug = isDebug;
exports.isElectron = isElectron;
exports.isGitHubCI = isGitHubCI;
exports.is_debug = is_debug;
exports.srcDir = srcDir;
exports.tmpDir = tmpDir;
