import json from '@rollup/plugin-json';
import { nodeResolve } from '@rollup/plugin-node-resolve';
import { execSync } from 'child_process';
import { globSync } from 'glob';
import path from 'path';
import { fileURLToPath } from 'url';
import pkg from './package.json' assert { type: 'json' };

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const deps = Object.keys(pkg.dependencies)
  .concat(...Object.keys(pkg.devDependencies))
  .concat('better-sqlite3', 'node-cache', '@whiskeysockets/baileys', 'pino', 'long', 'electron');
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

function buildWhatsapp() {
  execSync('tsc -p tsconfig.whatsapp.json', { stdio: 'inherit', cwd: __dirname });
  /**
   * @type {import('rollup').RollupOptions}
   */
  const whatsapp = {
    input: 'tmp/whatsapp/node_backend/index.js',
    output: {
      file: 'dist/whatsapp.js',
      format: 'esm',
      name: 'whatsapp_bot',
      globals
    },
    external: deps.concat('pino', 'node-cache', '@whiskeysockets/baileys', 'fs-extra', 'sbg-utility'),
    plugins: [
      nodeResolve({ preferBuiltins: true, extensions: ['.mjs', '.js', '.json', '.node', '.cjs', '.ts'] }),
      json()
    ]
  };
  return whatsapp;
}

const whatsapp_handlers = globSync('tmp/whatsapp/node_backend/whatsapp_handlers/*.{js,ts,cjs,mjs}', {
  cwd: __dirname,
  ignore: ['**/*.d.ts']
}).map((input) => {
  /**
   * @type {import('rollup').RollupOptions}
   */
  const handler = {
    input,
    output: {
      file: `dist/whatsapp_handlers/${path.basename(input)}`,
      format: 'esm',
      name: 'whatsapp_bot',
      globals
    },
    external: deps.concat('pino', 'node-cache', '@whiskeysockets/baileys', 'fs-extra', 'sbg-utility'),
    plugins: [
      nodeResolve({ preferBuiltins: true, extensions: ['.mjs', '.js', '.json', '.node', '.cjs', '.ts'] }),
      json()
    ]
  };
  return handler;
});

/**
 * Exports both the ESM and CommonJS configurations for Rollup to build.
 * @type {import('rollup').RollupOptions[]}
 */
export default [buildWhatsapp(), ...whatsapp_handlers];
