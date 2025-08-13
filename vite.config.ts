import react from '@vitejs/plugin-react';
import path from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vite';
import mkcert from 'vite-plugin-mkcert';
import { indexHtmlReplacementPlugin, TailwindCSSBuildPlugin } from './vite-plugin.js';
import 'dotenv/config';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const distPath = path.resolve(__dirname, 'dist/react');

export default defineConfig({
  root: '.',
  cacheDir: path.resolve(__dirname, 'tmp/.vite'),
  plugins: [TailwindCSSBuildPlugin(), react(), mkcert(), indexHtmlReplacementPlugin()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src')
    }
  },
  build: {
    outDir: distPath,
    emptyOutDir: true,
    rollupOptions: {
      // https://rollupjs.org/configuration-options/
      input: {
        app: './index.dev.html'
      },
      output: {
        manualChunks: {
          react: ['react', 'react-dom'],
          'react-router': ['react-router', 'react-router-dom'],
          // bootstrap: ['bootstrap', 'react-bootstrap'],
          highlight: ['highlight.js'],
          // 'nik-parser': ['nik-parser-jurusid'],
          moment: ['moment', 'moment-timezone'],
          axios: ['axios'],
          'deepmerge-ts': ['deepmerge-ts']
        },
        entryFileNames: function (chunkInfo) {
          const filename = chunkInfo.name;
          if (filename === 'app') {
            return 'index.html';
          }
          return `${filename}.js`;
        }
      }
    }
  },
  server: {
    host: process.env.VITE_HOSTNAME || 'dev.webmanajemen.com',
    port: parseInt(String(process.env.VITE_PORT)) || 5173,
    open: false,
    watch: {
      ignored: [
        '**/node_modules/**',
        '**/dist/**',
        '**/build/**',
        '**/coverage/**',
        '**/packages/**',
        '**/tmp/**',
        '**/transpile/**',
        '**/docs/**',
        '**/.yarn/**',
        '**/.cache/**',
        '**/.vscode/**',
        '**/.idea/**',
        '**/.git/**',
        '**/.github/**',
        '**/.husky/**',
        '**/public/**/*.json',
        '**/tests/**',
        '**/test/**'
      ],
      usePolling: true, // slower but reliable
      interval: 100
    }
  }
});
