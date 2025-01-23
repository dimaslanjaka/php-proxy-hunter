import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import resolve from '@rollup/plugin-node-resolve';
import terser from '@rollup/plugin-terser';
import fs from 'fs';
import * as glob from 'glob';
import jsonc from 'jsonc-parser';
import path from 'upath';
import { fileURLToPath } from 'url';
import { isDebug } from './src/func.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * @type {typeof import('./package.json')}
 */
const pkg = jsonc.parse(fs.readFileSync(path.join(__dirname, 'package.json'), 'utf-8'));
export const external = Object.keys(pkg.dependencies)
  .concat(...Object.keys(pkg.devDependencies), 'lodash', 'underscore')
  .filter((pkgName) => ![/*'markdown-it', */ 'p-limit', 'deepmerge-ts'].includes(pkgName));

/**
 * @type {import('rollup').RollupOptions}
 */
const proxyManager = {
  input: './proxyManager-src.js',
  output: {
    file: './proxyManager.js',
    format: 'iife'
  },
  plugins: [
    resolve({ preferBuiltins: true }), // Resolve node_modules packages
    json(), // Support for JSON files
    commonjs()
  ]
};
if (!isDebug()) {
  // Minify the output on production
  proxyManager.plugins.push(terser({ sourceMap: false }));
}
export { proxyManager };

const findJs = glob.globSync('views/assets/**/*.js', { cwd: __dirname }).map((f) => {
  return path.toUnix(f);
});
const phpJs = findJs.map((input) => {
  /**
   * @type {import('rollup').RollupOptions}
   */
  const config = {
    input,
    output: {
      file: `public/php/${input.replace('views/assets/', '')}`,
      format: 'iife'
    },
    plugins: [
      resolve({ preferBuiltins: true }), // Resolve node_modules packages
      json(), // Support for JSON files
      commonjs()
    ]
  };
  if (!isDebug()) {
    // Minify the output on production
    config.plugins.push(terser({ sourceMap: false }));
  }
  return config;
});

export default [proxyManager, ...phpJs];
