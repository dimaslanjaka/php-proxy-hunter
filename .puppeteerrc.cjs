const path = require('path');

/**
 * @type {import("puppeteer").Configuration}
 */
const config = {
  // Cache location for Puppeteer.
  cacheDirectory: path.join(__dirname, '.cache', 'puppeteer'),
  // Download Chrome (default `skipDownload: false`).
  chrome: {
    skipDownload: true
  },
  // Download Firefox (default `skipDownload: true`).
  firefox: {
    skipDownload: true
  },
  temporaryDirectory: path.join(__dirname, 'tmp/puppeteer')
};

const fs = require('fs');

fs.mkdirSync(config.cacheDirectory, { recursive: true });
fs.mkdirSync(config.temporaryDirectory, { recursive: true });

module.exports = config;
