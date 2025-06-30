import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import { nodeResolve } from '@rollup/plugin-node-resolve';
import typescript from '@rollup/plugin-typescript';
import { glob } from 'glob';

const external = ['fs', 'path', 'url', 'module', 'glob', 'markdown-it', 'markdown-it-anchor', 'nunjucks'];

/**
 * Creates a Rollup configuration object for a given module format and output directory.
 *
 * @param {string} format - The module format for the output (e.g., 'cjs', 'esm').
 * @param {string} outputDir - The directory where the build output will be placed.
 * @returns {import('rollup').RollupOptions} The Rollup configuration object.
 */
const createConfig = (format, outputDir) => ({
  external,
  plugins: [
    typescript({
      tsconfig: './tsconfig.json',
      declaration: true, // Generate declarations for both builds
      declarationDir: `dist/${outputDir}`,
      outDir: `dist/${outputDir}`,
      rootDir: 'src'
    }),
    nodeResolve({
      preferBuiltins: true
    }),
    commonjs(),
    json()
  ],
  output: {
    format,
    dir: `dist/${outputDir}`,
    preserveModules: true,
    preserveModulesRoot: 'src',
    exports: 'named'
  }
});

// Get all TypeScript files in src directory
const inputFiles = glob.sync('src/**/*.ts', { nodir: true });

/** @type {import('rollup').RollupOptions[]} */
const rollupConfig = [
  // ESM build
  {
    input: inputFiles,
    ...createConfig('es', 'esm')
  },
  // CJS build
  {
    input: inputFiles,
    ...createConfig('cjs', 'cjs'),
    output: {
      ...createConfig('cjs', 'cjs').output,
      banner: (chunk) => {
        // Add shebang only to the binary file
        return chunk.fileName === 'build-gh-pages.js' ? '#!/usr/bin/env node' : '';
      }
    }
  }
];

export default rollupConfig;
