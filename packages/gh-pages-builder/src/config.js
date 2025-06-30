/**
 * ESM Config Loader for GitHub Pages Builder
 * Auto-detects and handles both sync and async config functions
 */

import fs from 'fs';
import path from 'path';
import { pathToFileURL } from 'url';

const projectDir = process.cwd();
const configFilenames = ['gh-pages-builder.config.cjs', 'gh-pages-builder.config.mjs', 'gh-pages-builder.config.js'];

/**
 * Find the first existing config file
 * @returns {Promise<string|null>} Path to config file or null if none found
 */
async function findConfigFile() {
  for (const filename of configFilenames) {
    const configPath = path.join(projectDir, filename);
    try {
      await fs.promises.access(configPath);
      return configPath;
    } catch {
      // File doesn't exist, continue to next
    }
  }
  return null;
}

/**
 * Load configuration from gh-pages-builder.config.* files
 * Automatically detects and handles both sync and async config functions
 * Supports .cjs, .mjs, and .js extensions
 * @returns {Promise<object>} Configuration object
 */
export async function loadConfig() {
  let loadedConfig = {};

  try {
    // Find the first available config file
    const configPath = await findConfigFile();

    if (!configPath) {
      console.log(`ℹ️  No config file found (tried: ${configFilenames.join(', ')}), using defaults`);
      return loadedConfig;
    }

    // Determine how to load based on file extension
    const ext = path.extname(configPath);
    let configModule;

    if (ext === '.cjs') {
      // Load CommonJS module using createRequire
      const { createRequire } = await import('module');
      const require = createRequire(import.meta.url);

      // Clear require cache to allow hot reloading
      delete require.cache[require.resolve(configPath)];
      configModule = require(configPath);
    } else if (ext === '.mjs' || ext === '.js') {
      // Load ESM module using dynamic import with cache busting
      const cacheBustUrl = `${pathToFileURL(configPath).href}?t=${Date.now()}`;
      const imported = await import(cacheBustUrl);
      configModule = imported.default || imported;
    }

    // Handle different export types
    if (typeof configModule === 'function') {
      // Call the function
      const result = configModule();

      // Check if result is a Promise (async function)
      if (result && typeof result.then === 'function') {
        loadedConfig = await result;
      } else {
        loadedConfig = result;
      }
    } else {
      // Direct object export
      loadedConfig = configModule;
    }

    console.log(`✅ Loaded config from ${configPath}`);
  } catch (err) {
    console.warn(`⚠️  Could not load config:`, err.message);
  }

  return loadedConfig;
}

/**
 * Universal config loader that can handle both sync and async usage patterns (ESM version)
 * Note: This ESM version is primarily async due to dynamic import requirements
 * @returns {Promise<object>} Configuration object
 */
export async function loadConfigUniversal() {
  return await loadConfig();
}

/**
 * Get default configuration
 * @returns {object} Default configuration object
 */
export function getDefaultConfig() {
  return {
    inputPattern: '**/*.md',
    outputDir: 'tmp/docs',
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
    }
  };
}

/**
 * Load configuration with defaults merged
 * @returns {Promise<object>} Configuration object with defaults
 */
export async function loadConfigWithDefaults() {
  const defaults = getDefaultConfig();
  const userConfig = await loadConfig();

  // Deep merge configuration
  return {
    ...defaults,
    ...userConfig,
    processing: {
      ...defaults.processing,
      ...(userConfig.processing || {})
    },
    ignorePatterns: userConfig.ignorePatterns || defaults.ignorePatterns
  };
}

export { configFilenames };
