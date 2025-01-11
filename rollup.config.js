import babel from '@rollup/plugin-babel';
import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import { nodeResolve } from '@rollup/plugin-node-resolve';
import replace from '@rollup/plugin-replace';
import { execSync } from 'child_process';
import { deepmerge } from 'deepmerge-ts';
import fs from 'fs-extra';
import { globSync } from 'glob';
import path from 'path';
import nodePolyfills from 'rollup-plugin-polyfill-node';
import { md5 } from 'sbg-utility';
import { fileURLToPath } from 'url';
import pkg from './package.json' assert { type: 'json' };

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const deps = Object.keys(pkg.dependencies)
  .concat(...Object.keys(pkg.devDependencies))
  .concat(
    'express',
    'express-session',
    'express-socket.io-session',
    'socket.io',
    'socket.io-client',
    'electron',
    'better-sqlite3',
    'node-cache',
    '@whiskeysockets/baileys',
    'pino'
  );
const globals = {
  jquery: '$',
  lodash: '_',
  axios: 'axios'
};

const _esmBanner = `
import * as NodeUrl from 'node:url';
import NodePath from 'node:path';

if (typeof __dirname === 'undefined') {
  const __filename = NodeUrl.fileURLToPath(import.meta.url);
  const __dirname = NodePath.dirname(__filename);
}
`;

/**
 A Rollup plugin that modifies the output code after the build process.

 @returns {Object} - A Rollup plugin object.
*/
const modifyOutputCode = () => {
  return {
    name: 'modify-output-code',
    async writeBundle(options) {
      const outputFilePath = path.resolve(options.dir || '', options.file || 'dist/bundle.js');

      try {
        // Read the generated output file
        let code = await fs.readFile(outputFilePath, 'utf-8');

        // Modify the code with the specified replacements
        code = code
          .replace(/require\('u'\s*\+\s*'rl'\)/g, "require('url')")
          .replace("require('u' + 'rl')", "require('url')");

        // Write the modified code back to the file
        await fs.writeFile(outputFilePath, code, 'utf-8');
      } catch (error) {
        console.error('Error modifying output code:', error);
      }
    }
  };
};

/**
 * Base Rollup configuration shared between ESM and CommonJS builds.
 * Contains input and common plugins.
 * @type {import('rollup').RollupOptions}
 */
const baseConfig = {
  input: './index.js', // Entry point
  /**
   * Plugins used for processing various tasks.
   * @type {import('rollup').Plugin[]}
   */
  plugins: [
    json(), // Resolve json
    nodeResolve({ preferBuiltins: true, extensions: ['.mjs', '.js', '.json', '.node', '.cjs'] }),
    commonjs(), // Handle CommonJS modules
    replace({
      values: {
        'process.env.DEBUG': 'false'
      },
      preventAssignment: true
    }),
    modifyOutputCode()
  ],
  external: deps
  // output: {
  //   globals
  // }
};

/**
 * Rollup configuration for building the ESM (ECMAScript Module) output.
 * @type {import('rollup').RollupOptions}
 */
const mainConfig = deepmerge(baseConfig, {
  output: {
    file: 'app/main.js',
    format: 'cjs',
    sourcemap: false, // Include sourcemap
    inlineDynamicImports: true // inline dynamic imports if needed
  }
});

/**
 * Rollup configuration for building the ESM (ECMAScript Module) output.
 * @type {import('rollup').RollupOptions}
 */
const esmConfig = deepmerge(baseConfig, {
  output: {
    file: 'app/main.mjs',
    format: 'esm',
    sourcemap: false,
    inlineDynamicImports: true
    // banner: _esmBanner
  }
});

/**
 * Rollup configuration for building the CommonJS (ES5) output.
 * Includes Babel plugin for transpiling code to ES5.
 * @type {import('rollup').RollupOptions}
 */
const es5Config = {
  input: baseConfig.input, // Entry point
  output: {
    file: 'app/main.cjs', // CommonJS output file
    format: 'cjs', // CommonJS format with require()
    sourcemap: false, // Include sourcemap
    inlineDynamicImports: true // inline dynamic imports if needed
  },
  external: deps,
  /**
   * Plugins specific to the CommonJS build.
   * This includes Babel for transpiling to ES5.
   * @type {import('rollup').Plugin[]}
   */
  plugins: [
    json(), // Resolve json
    nodeResolve({ preferBuiltins: true, extensions: ['.mjs', '.js', '.json', '.node', '.cjs'] }),
    commonjs(), // Handle CommonJS modules
    replace({
      values: {
        'process.env.DEBUG': 'false'
      },
      preventAssignment: true
    }),
    babel({
      babelHelpers: 'bundled', // Use bundled helpers to avoid multiple imports
      presets: ['@babel/preset-env'], // Transpile to ES5
      exclude: 'node_modules/**' // Exclude node_modules from transpilation
    }),
    modifyOutputCode()
  ]
};

/**
 * @type {import('rollup').RollupOptions[]}
 */
const expressRes = globSync('**/*.{js,cjs}', { cwd: 'node_browser/express', ignore: ['**/utils/**'] }).map((f) => {
  /**
   * @type {import('rollup').RollupOptions}
   */
  const currentConfig = {
    input: path.join(__dirname, 'node_browser', 'express', f),
    output: {
      file: path.join(__dirname, 'public', 'static', 'express', f),
      format: 'iife',
      name: '_' + md5(f),
      globals
    },
    plugins: [commonjs(), nodeResolve({ browser: true }), json(), nodePolyfills()]
  };
  return currentConfig;
});

/**
 * @type {import('rollup').RollupOptions}
 */
const _env = {
  input: '.env.mjs',
  output: {
    file: '.env.cjs',
    format: 'cjs'
  },
  external: deps,
  plugins: [nodeResolve({ preferBuiltins: true })]
};

/**
 * @type {import('rollup').RollupOptions}
 */
const _whatsapp_xl = {
  input: 'tmp/whatsapp/node_backend/index.js',
  output: {
    file: 'dist/whatsapp.js',
    format: 'esm',
    name: 'whatsapp_bot',
    globals
  },
  external: deps.concat('pino', 'node-cache', '@whiskeysockets/baileys', 'fs-extra', 'sbg-utility'),
  plugins: [
    {
      name: 'run-shell-command',
      buildStart() {
        execSync('tsc -p tsconfig.whatsapp.json', { stdio: 'inherit', cwd: __dirname });
      }
    },
    nodeResolve({ preferBuiltins: true, extensions: ['.mjs', '.js', '.json', '.node', '.cjs', '.ts'] })
  ]
};

/**
 * Exports both the ESM and CommonJS configurations for Rollup to build.
 * @type {import('rollup').RollupOptions[]}
 */
export default [_whatsapp_xl, _env, mainConfig, esmConfig, es5Config, ...expressRes].filter((config) =>
  fs.existsSync(config.input)
);
