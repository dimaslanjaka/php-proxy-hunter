import babel from '@rollup/plugin-babel';
import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import { nodeResolve } from '@rollup/plugin-node-resolve';
import replace from '@rollup/plugin-replace';
import { deepmerge } from 'deepmerge-ts';
import fs from 'fs-extra';
import { globSync } from 'glob';
import path from 'path';
import nodePolyfills from 'rollup-plugin-polyfill-node';
import { md5 } from 'sbg-utility';
import { fileURLToPath, pathToFileURL } from 'url';
import pkg from './package.json' with { type: 'json' };

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
      generatorOpts: { importAttributesKeyword: 'with' },
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
  plugins: [nodeResolve({ preferBuiltins: true }), json()]
};

/**
 * Exports both the ESM and CommonJS configurations for Rollup to build.
 * @returns {Promise<import('rollup').RollupOptions[]>} A promise that resolves to an array of Rollup configuration objects.
 */
async function configResolver() {
  const configs = [_env, mainConfig, esmConfig, es5Config, ...expressRes];

  // Define paths for dynamic configuration files
  const dynamicConfigs = [path.join(__dirname, 'rollup.whatsapp.js'), path.join(__dirname, 'rollup.php.js')];

  // Loop through dynamicConfigs and add them to configs if they exist
  for (const dynamicConfig of dynamicConfigs) {
    if (fs.existsSync(dynamicConfig)) {
      // Convert the file path to file URL (required for dynamic import)
      const fileUrl = pathToFileURL(dynamicConfig).href;

      /**
       * Dynamically import the config file and extract the default export
       * @type {import('rollup').RollupOptions|import('rollup').RollupOptions[]}
       */
      const importedConfig = await import(fileUrl).then((lib) => lib.default);

      // If the imported config is an array, push each item to the configs array
      if (Array.isArray(importedConfig)) {
        for (const objectConfig of importedConfig) {
          configs.push(objectConfig);
        }
      } else {
        // Otherwise, push the single config object
        configs.push(importedConfig);
      }
    }
  }

  // Filter configs to only include those with a valid 'input' property
  return configs.filter((config) => fs.existsSync(config.input));
}

export default configResolver();
