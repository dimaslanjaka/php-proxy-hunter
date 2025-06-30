import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import { nodeResolve } from '@rollup/plugin-node-resolve';
import typescript from '@rollup/plugin-typescript';

const external = ['fs', 'path', 'url', 'module', 'glob', 'markdown-it', 'markdown-it-anchor'];

const createConfig = (format, outputDir) => ({
  external,
  plugins: [
    typescript({
      tsconfig: './tsconfig.json',
      declaration: format === 'es',
      declarationDir: format === 'es' ? `dist/${outputDir}` : undefined,
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

export default [
  // ESM build
  {
    input: ['src/index.ts', 'src/config.ts', 'src/build-gh-pages.ts'],
    ...createConfig('es', 'esm')
  },
  // CJS build
  {
    input: ['src/index.ts', 'src/config.ts', 'src/build-gh-pages.ts'],
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
