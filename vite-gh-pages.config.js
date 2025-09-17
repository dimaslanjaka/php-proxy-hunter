import path from 'upath';
import { fileURLToPath } from 'url';
import { mergeConfig } from 'vite';
import { viteConfig as importViteConfig } from './vite.config.js';
import dotenv from 'dotenv';

/**
 * Fixes __dirname for ESM modules.
 * @type {string}
 */
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const isGithubCI = process.env.GITHUB_ACTIONS === 'true';
const sampleCfg = dotenv.config({ path: path.resolve(__dirname, '.env.example') });

if (isGithubCI) {
  // In GitHub CI, ensure all variables from .env.example are set, using defaults if not provided in secrets
  for (const [key, _value] of Object.entries(sampleCfg.parsed || {})) {
    const deviceValue = process.env[key];
    if (deviceValue) {
      if (key.startsWith('VITE_')) {
        process.env[key] = deviceValue;
      } else {
        process.env[`VITE_${key}`] = deviceValue;
      }
    }
  }
}

/**
 * Vite configuration merged with GitHub Pages base path.
 * @type {import('vite').UserConfig}
 */
const viteConfig = mergeConfig(importViteConfig, { base: '/php-proxy-hunter/' });

export default viteConfig;
