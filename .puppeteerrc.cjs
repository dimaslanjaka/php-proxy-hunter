const { join } = require('path');

/**
 * @type {import("puppeteer").Configuration}
 */
module.exports = {
  // Cache location for Puppeteer.
  cacheDirectory: join(__dirname, '.cache', 'puppeteer')
};
