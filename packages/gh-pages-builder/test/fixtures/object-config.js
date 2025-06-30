/**
 * GitHub Pages Builder Configuration - Direct Object Export
 * This file demonstrates the simplest config pattern
 */

export default {
  inputPattern: '**/*.md',
  outputDir: {
    markdown: 'tmp/markdown',
    html: 'tmp/html'
  },
  ignorePatterns: ['**/node_modules/**', '**/dist/**', '**/tmp/**'],
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
  }
};
