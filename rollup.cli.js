import babel from '@rollup/plugin-babel';
import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import { nodeResolve } from '@rollup/plugin-node-resolve';

const input = ['src/cli/python-wrapper-cli.cjs'];

/** @type {import('rollup').RollupOptions} */
const config = {
  input,
  output: {
    dir: 'dist/cli',
    format: 'cjs',
    entryFileNames: '[name].cjs',
    banner: '#!/usr/bin/env node'
  },
  external: ['child_process', 'fs', 'path', 'os', 'stream'],
  plugins: [
    json(),
    nodeResolve({ preferBuiltins: true, extensions: ['.mjs', '.js', '.json', '.node', '.cjs'] }),
    commonjs(),
    babel({
      babelHelpers: 'bundled',
      presets: ['@babel/preset-env'],
      exclude: 'node_modules/**'
    })
  ]
};

export default config;
