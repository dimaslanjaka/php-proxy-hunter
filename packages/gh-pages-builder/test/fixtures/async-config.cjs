/**
 * GitHub Pages Builder Configuration - CommonJS Async Function
 * This file demonstrates async config pattern for CommonJS
 */

module.exports = async function () {
  // Simulate async operation (e.g., reading from API, database, etc.)
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve({
        inputPattern: '**/*.async.md',
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
        customOption: 'async-test'
      });
    }, 10);
  });
};
