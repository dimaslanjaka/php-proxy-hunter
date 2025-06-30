/**
 * GitHub Pages Builder Configuration - CommonJS Sync Function
 * This file demonstrates sync function config pattern for CommonJS
 */

module.exports = function () {
  return {
    inputPattern: '**/*.test.md',
    outputDir: {
      markdown: 'tmp/markdown',
      html: 'tmp/html'
    },
    customOption: 'sync-test'
  };
};
