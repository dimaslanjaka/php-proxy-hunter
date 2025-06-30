/**
 * GitHub Pages Builder Configuration - CommonJS Sync Function
 * This file demonstrates sync function config pattern for CommonJS
 */

module.exports = function() {
  return {
    inputPattern: '**/*.test.md',
    outputDir: 'test-output',
    customOption: 'sync-test'
  };
};
