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
    generateToc: false,
    enableAnchors: true,
    tocIndentSize: 2
  },
  theme: {
    name: 'default',
    engine: 'nunjucks'
  },
  directExport: true
};
