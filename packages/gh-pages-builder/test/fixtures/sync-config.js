/**
 * GitHub Pages Builder Configuration - Sync Function Export
 * This file demonstrates sync function config pattern
 */

export default function () {
  return {
    /**
     * Glob pattern to find markdown files
     */
    inputPattern: '**/*.md',

    /**
     * Output directory for processed files
     */
    outputDir: {
      markdown: 'tmp/markdown',
      html: 'tmp/html'
    },

    /**
     * Patterns to ignore when searching for markdown files
     */
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

    /**
     * Regular expression to match TOC placeholder in markdown
     */
    tocPlaceholder: /<!--\s*toc\s*-->/i,

    /**
     * Whether to rename README.md files to index.md
     */
    renameReadme: true,

    /**
     * Custom processing options
     */
    processing: {
      generateToc: true,
      enableAnchors: true,
      tocIndentSize: 2
    }
  };
}
