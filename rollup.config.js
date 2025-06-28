import fs from 'fs-extra';
import { globSync } from 'glob';
import path from 'path';
import { fileURLToPath, pathToFileURL } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Exports both the ESM and CommonJS configurations for Rollup to build.
 * @returns {Promise<import('rollup').RollupOptions[]>} A promise that resolves to an array of Rollup configuration objects.
 */
async function configResolver() {
  /**
   * @type {import('rollup').RollupOptions[]}
   */
  const configs = [];

  // Define paths for dynamic configuration files
  const dynamicConfigs = [
    ...globSync('rollup.*.js', { cwd: __dirname }).map((f) => path.join(__dirname, f)),
    ...globSync('rollup.*.cjs', { cwd: __dirname }).map((f) => path.join(__dirname, f)),
    ...globSync('rollup.*.mjs', { cwd: __dirname }).map((f) => path.join(__dirname, f))
  ].filter((v, i, a) => a.indexOf(v) === i);

  // Helper to timeout if import hangs
  const timeout = (ms) => new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout loading config')), ms));

  // Loop through dynamicConfigs and add them to configs if they exist
  for (const dynamicConfig of dynamicConfigs) {
    if (!fs.existsSync(dynamicConfig)) continue;

    const fileUrl = pathToFileURL(dynamicConfig).href;
    console.log(`[IMPORT] Trying: ${dynamicConfig}`);

    try {
      const lib = await Promise.race([
        import(fileUrl),
        timeout(3000) // 3 second timeout
      ]);

      const importedConfig = lib?.default ?? lib;
      if (!importedConfig) {
        throw new Error(`No valid export found in ${dynamicConfig}`);
      }

      if (Array.isArray(importedConfig)) {
        for (const config of importedConfig) {
          if (!config.input) {
            throw new Error(`Invalid config in ${dynamicConfig}: missing 'input' property`);
          }
        }
        configs.push(...importedConfig);
      } else {
        configs.push(importedConfig);
        if (!importedConfig.input) {
          throw new Error(`Invalid config in ${dynamicConfig}: missing 'input' property`);
        }
      }

      console.log(`[OK] Loaded dynamic config from: ${dynamicConfig}`);
    } catch (err) {
      console.error(`[FAIL] Failed to load ${dynamicConfig}: ${err.message}`);
    }
  }

  console.log(`Total configurations loaded: ${configs.length}`);
  return configs;
}

export default configResolver();
