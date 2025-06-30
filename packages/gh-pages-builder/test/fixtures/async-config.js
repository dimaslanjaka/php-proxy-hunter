/**
 * GitHub Pages Builder Configuration - Async Function Export
 * This file demonstrates async config pattern
 */

export default async function () {
  // Simulate async operation (e.g., reading from API, database, etc.)
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve({
        inputPattern: '**/*.md',
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
        renameReadme: true
      });
    }, 100);
  });
}
