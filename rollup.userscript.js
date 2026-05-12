import babel from '@rollup/plugin-babel';
import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import { nodeResolve } from '@rollup/plugin-node-resolve';
import typescript from '@rollup/plugin-typescript';
import fs from 'fs-extra';
import path from 'path';

const metaPath = path.join(process.cwd(), 'src', 'userscripts', 'universal.meta.js');
const metaContent = fs.readFileSync(metaPath, 'utf-8');

export default {
  input: 'src/userscripts/universal.user.ts',
  output: {
    file: 'userscripts/universal.user.js',
    format: 'iife',
    name: 'UniversalUserScript',
    banner: metaContent
  },
  plugins: [
    nodeResolve(),
    commonjs(),
    json(),
    typescript({
      tsconfig: false,
      compilerOptions: {
        target: 'ES5',
        module: 'ESNext',
        moduleResolution: 'Node',
        allowSyntheticDefaultImports: true,
        esModuleInterop: true,
        declaration: false,
        sourceMap: false
      },
      include: ['src/userscripts/**/*.ts'],
      exclude: ['node_modules/**', 'dist/**']
    }),
    babel({
      babelHelpers: 'bundled',
      extensions: ['.js', '.ts']
    })
  ]
};
