/**
 * GitHub Pages Builder Configuration - Direct Object Export
 * This file demonstrates the simplest config pattern
 */

export default {
  inputPattern: '**/*.md',
  outputDir: 'tmp/docs',
  ignorePatterns: ['**/node_modules/**', '**/dist/**'],
  tocPlaceholder: /<!--\s*toc\s*-->/i,
  renameReadme: true
};
