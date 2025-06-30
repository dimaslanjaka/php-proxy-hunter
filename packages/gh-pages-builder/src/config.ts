/**
 * ESM Config Loader for GitHub Pages Builder
 * Auto-detects and handles both sync and async config functions
 */

import fs from 'fs';
import path from 'path';
import { pathToFileURL } from 'url';
import { projectDir } from './init.js';
import console from './logger.js';

const configFilenames = ['gh-pages-builder.config.cjs', 'gh-pages-builder.config.mjs', 'gh-pages-builder.config.js'];

export interface Config {
  inputPattern: string;
  outputDir: {
    markdown: string;
    html: string;
  };
  ignorePatterns: string[];
  tocPlaceholder: RegExp;
  renameReadme: boolean;
  processing: {
    generateToc: boolean;
    enableAnchors: boolean;
    tocIndentSize: number;
  };
  theme: {
    name: string;
    engine: 'nunjucks' | 'handlebars' | 'mustache' | 'ejs';
  };
}

/**
 * Get default configuration
 * @returns Default configuration object
 */
export function getDefaultConfig(): Config {
  return {
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
      '**/tmp/**'
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
    }
  };
}

/**
 * Find the first existing config file
 * @returns Path to config file or null if none found
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
 * @returns Configuration object
 */
export async function loadConfig(): Promise<ReturnType<typeof getDefaultConfig>> {
  let loadedConfig = {} as ReturnType<typeof getDefaultConfig>;

  try {
    // Find the first available config file
    const configPath = await findConfigFile();

    if (!configPath) {
      throw new Error(`No config file found (tried: ${configFilenames.join(', ')})`);
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
    if (err && typeof err === 'object' && 'message' in err) {
      console.warn(`⚠️  Could not load config:`, (err as { message: string }).message);
    } else {
      console.warn(`⚠️  Could not load config:`, err);
    }
  }

  return loadedConfig;
}

/**
 * Load configuration with defaults merged
 * @returns Configuration object with defaults
 */
export async function loadConfigWithDefaults() {
  const defaults = getDefaultConfig();
  const userConfig = await loadConfig();

  // Deep merge configuration
  const loadedConfig = {
    ...defaults,
    ...userConfig,
    processing: {
      ...defaults.processing,
      ...(userConfig.processing || {})
    },
    ignorePatterns: userConfig.ignorePatterns || defaults.ignorePatterns
  };

  // Ensure outputDir is always an object with markdown and html subdirectories
  if (loadedConfig.outputDir && typeof loadedConfig.outputDir === 'string') {
    // If outputDir is a string, migrate value to object with markdown and html subdirectories
    loadedConfig.outputDir = {
      markdown: loadedConfig.outputDir,
      html: loadedConfig.outputDir
    };
  }
  return loadedConfig;
}

export { configFilenames };
