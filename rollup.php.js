import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import resolve from '@rollup/plugin-node-resolve';
import terser from '@rollup/plugin-terser';
import fs from 'fs';
import jsonc from 'jsonc-parser';
import path from 'path';
import { fileURLToPath } from 'url';

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
export const proxyManager = {
  input: './proxyManager-src.js',
  output: {
    file: './proxyManager.js',
    format: 'iife'
  },
  plugins: [
    json(), // Support for JSON files
    resolve({ preferBuiltins: true }), // Resolve node_modules packages
    commonjs(),
    terser({ sourceMap: false }) // Minify the output
  ]
};
