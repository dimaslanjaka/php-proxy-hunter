import path from 'upath';
import { fileURLToPath } from 'url';
import { mergeConfig } from 'vite';
import { viteConfig as importViteConfig } from './vite.config.js';

/**
 * Fixes __dirname for ESM modules.
 * @type {string}
 */
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Vite configuration merged with GitHub Pages base path.
 * @type {import('vite').UserConfig}
 */
const viteConfig = mergeConfig(importViteConfig, { base: '/php-proxy-hunter/' });

export default viteConfig;
