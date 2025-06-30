/**
 * GitHub Pages Builder Configuration - CommonJS Direct Object
 * This file demonstrates direct object export for CommonJS
 */

module.exports = {
  inputPattern: '**/*.direct.md',
  outputDir: {
    markdown: 'tmp/markdown',
    html: 'tmp/html'
  },
  directExport: true,
  processing: {
    generateToc: false
  }
};
