/**
 * @fileoverview GitHub Pages Builder - Main Entry Point
 * @description Exports all public API functions for the GitHub Pages Builder
 */

// Export configuration functions
export { configFilenames, getDefaultConfig, loadConfig, loadConfigWithDefaults } from './config.js';

// Export utility functions from build script
export { renderTocFromMarkdown, slugify } from './build-gh-pages.js';

// Re-export for convenience
export { default as buildGitHubPages } from './build-gh-pages.js';
