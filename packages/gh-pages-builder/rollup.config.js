import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import { nodeResolve } from '@rollup/plugin-node-resolve';

const external = ['fs', 'path', 'url', 'module', 'glob', 'markdown-it', 'markdown-it-anchor'];

const createConfig = (format, outputDir) => ({
  external,
  plugins: [
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
    input: ['src/index.js', 'src/config.js', 'src/build-gh-pages.js'],
    ...createConfig('es', 'esm')
  },
  // CJS build
  {
    input: ['src/index.js', 'src/config.js', 'src/build-gh-pages.js'],
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
