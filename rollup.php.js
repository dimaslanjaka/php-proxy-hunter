import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import resolve from '@rollup/plugin-node-resolve';
import terser from '@rollup/plugin-terser';
import fs from 'fs';
import * as glob from 'glob';
import jsonc from 'jsonc-parser';
import * as sass from 'sass';
import { bindProcessExit, writefile } from 'sbg-utility';
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
  .concat(
    ...Object.keys(pkg.devDependencies),
    'lodash',
    'underscore',
    'browser-with-fingerprints',
    'puppeteer-with-fingerprints',
    'selenium-with-fingerprints'
  )
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
    commonjs(),
    {
      name: 'build-php-env',
      buildStart: () => {
        const file = path.join(__dirname, 'public', 'php', 'json', 'env.json');
        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, JSON.stringify({ build: Math.random().toString(36).slice(2) }));
        console.log('PHP env file created:', file);
      },
      closeBundle: () => {
        // Copy views/assets/json to public/php/json
        const srcDir = path.join(__dirname, 'views', 'assets', 'json');
        const destDir = path.join(__dirname, 'public', 'php', 'json');
        fs.mkdirSync(destDir, { recursive: true });
        glob.globSync('*.{json,jsonc,txt}', { cwd: srcDir }).forEach((file) => {
          const srcFile = path.join(srcDir, file);
          const destFile = path.join(destDir, file);
          fs.copyFileSync(srcFile, destFile);
          console.log('Copied', srcFile, 'to', destFile);
        });
      }
    }
  ]
};
if (!isDebug() && Array.isArray(proxyManager.plugins)) {
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
      format: 'iife',
      name: sanitizeExportName(path.basename(input, '.js'))
    },
    plugins: [
      resolve({ preferBuiltins: true }), // Resolve node_modules packages
      json(), // Support for JSON files
      commonjs()
    ]
  };
  if (!isDebug() && Array.isArray(config.plugins)) {
    // Minify the output on production
    config.plugins.push(terser({ sourceMap: false }));
  }
  return config;
});

export default [proxyManager, ...phpJs];

/**
 * Sanitizes a string to be a valid JavaScript identifier.
 * @param {string} name - The name to sanitize.
 * @returns {string} The sanitized name.
 */
function sanitizeExportName(name) {
  return name
    .replace(/[^a-zA-Z0-9_$]+/g, '_') // Replace one or more invalid characters with a single underscore.
    .replace(/^[^a-zA-Z_$]/, '_') // Ensure the name starts with a valid character.
    .replace(/_+$/, ''); // Remove trailing underscores.
}

function compileSass() {
  const resources = glob.globSync('views/assets/**/*.scss', { cwd: __dirname }).map((f) => {
    const input = path.toUnix(f);
    return {
      input,
      output: `public/php/${input.replace('views/assets/', '').replace('.scss', '.css')}`
    };
  });
  for (let i = 0; i < resources.length; i++) {
    const src = resources[i];
    const result = sass.compile(src.input, { charset: true, style: !isDebug() ? 'compressed' : 'expanded' });
    writefile(src.output, result.css);
    console.log('compiled', src.output);
  }
}

bindProcessExit('compileSass', function () {
  compileSass();
});
