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
        customOption: 'async-test',
        processing: {
          generateToc: true,
          enableAnchors: true,
          tocIndentSize: 2
        },
        theme: {
          name: 'default',
          engine: 'nunjucks'
        }
      });
    }, 10);
  });
};
