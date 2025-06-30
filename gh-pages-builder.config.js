/**
 * GitHub Pages Builder Configuration
 * This file demonstrates both sync and async config patterns
 */

// Example of async config (uncomment to test async functionality)
// module.exports = async function() {
//   // Simulate async operation (e.g., reading from API, database, etc.)
//   return new Promise((resolve) => {
//     setTimeout(() => {
//       resolve({
//         inputPattern: '**/*.md',
//         outputDir: 'tmp/docs',
//         ignorePatterns: [
//           '**/node_modules/**',
//           '**/dist/**',
//           '**/build/**',
//           '**/.git/**',
//           '**/coverage/**',
//           '**/docs/**',
//           '**/test/**',
//           '**/tests/**',
//           '**/vendor/**',
//           '**/composer/**',
//           '**/simplehtmldom/**'
//         ],
//         tocPlaceholder: /<!--\s*toc\s*-->/i,
//         renameReadme: true
//       });
//     }, 100);
//   });
// };

// Example of sync function config
module.exports = function () {
  return {
    /**
     * Glob pattern to find markdown files
     */
    inputPattern: '**/*.md',

    /**
     * Output directory for processed files
     */
    outputDir: 'tmp/docs',

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
};

// Example of direct object export (simplest form)
// module.exports = {
//   inputPattern: '**/*.md',
//   outputDir: 'tmp/docs',
//   ignorePatterns: ['**/node_modules/**', '**/dist/**'],
//   tocPlaceholder: /<!--\s*toc\s*-->/i,
//   renameReadme: true
// };
