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
    ignorePatterns: [
      '**/node_modules/**',
      '**/dist/**',
      '**/build/**',
      '**/.git/**',
      '**/coverage/**',
      '**/docs/**',
      '**/test/**',
      '**/tests/**',
      '**/vendor/**',
      '**/composer/**',
      '**/simplehtmldom/**'
    ],
    tocPlaceholder: /<!--\s*toc\s*-->/i,
    renameReadme: true,
    processing: {
      generateToc: true,
      enableAnchors: true,
      tocIndentSize: 2
    },
    theme: {
      name: 'default',
      engine: 'nunjucks'
    },
    customOption: 'sync-test'
  };
};
