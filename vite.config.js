import react from '@vitejs/plugin-react';
import 'dotenv/config';
import path from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vite';
import mkcert from 'vite-plugin-mkcert';
import { indexHtmlReplacementPlugin, TailwindCSSBuildPlugin } from './vite-plugin.js';
import { execSync } from 'child_process';
import legacy from '@vitejs/plugin-legacy';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const distPath = path.resolve(__dirname, 'dist/react');
const gitCommitHash = execSync('git rev-parse --short HEAD').toString().trim();

export const viteConfig = defineConfig({
  root: '.',
  define: {
    'import.meta.env.VITE_GIT_COMMIT': `"${gitCommitHash}"`,
    VITE_GIT_COMMIT: `"${gitCommitHash}"`
  },
  cacheDir: path.resolve(__dirname, 'tmp/.vite'),
  plugins: [
    TailwindCSSBuildPlugin(),
    react(),
    mkcert(),
    indexHtmlReplacementPlugin(),
    legacy({
      targets: ['defaults', 'not IE 11']
    })
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
      '/assets/fonts': path.resolve(__dirname, 'assets/fonts')
    }
  },
  build: {
    outDir: distPath,
    emptyOutDir: true,
    minify: false,
    rollupOptions: {
      // https://rollupjs.org/configuration-options/
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
        entryFileNames: `assets/[name].[hash].js`,
        chunkFileNames: `assets/[name].[hash].js`,
        assetFileNames: (assetInfo) => {
          // Group assets by type: fonts, json, images, css, other
          let names = [];
          if (Array.isArray(assetInfo.names) && assetInfo.names.length > 0) {
            names = assetInfo.names;
          } else if (typeof assetInfo.name === 'string') {
            names = [assetInfo.name];
          }
          // Check all possible names for type
          for (const name of names) {
            if (/\.(woff2?|ttf|otf|eot)$/i.test(name)) {
              return `assets/fonts/[name].[hash][extname]`;
            }
          }
          for (const name of names) {
            if (/\.css$/i.test(name)) {
              return `assets/css/[name].[hash][extname]`;
            }
          }
          for (const name of names) {
            if (/\.json$/i.test(name)) {
              return `assets/json/[name].[hash][extname]`;
            }
          }
          for (const name of names) {
            if (/\.(png|jpe?g|svg|gif|webp|ico)$/i.test(name)) {
              return `assets/images/[name].[hash][extname]`;
            }
          }
          // Default: other assets
          return `assets/other/[name].[hash][extname]`;
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
        '**/test/**',
        '**/.deploy_git/**'
      ],
      usePolling: true, // slower but reliable
      interval: 100
    }
  }
});

export default viteConfig;
