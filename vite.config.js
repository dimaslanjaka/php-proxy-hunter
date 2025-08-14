// import react from '@vitejs/plugin-react';
import react from '@vitejs/plugin-react';
import path from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vite';
import mkcert from 'vite-plugin-mkcert';
import { indexHtmlReplacementPlugin, TailwindCSSBuildPlugin } from './vite-plugin.js';
import 'dotenv/config';
import { execSync } from 'child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const distPath = path.resolve(__dirname, 'dist/react');

// Get latest git commit hash (short)
let gitHash = 'dev';
try {
  gitHash = execSync('git rev-parse --short HEAD', { encoding: 'utf8' }).trim();
} catch {
  console.warn('Could not get git commit hash, using "dev"');
}

export const viteConfig = defineConfig({
  root: '.',
  // Uncomment below to test custom base path
  // base: '/php-proxy-hunter/',
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
        entryFileNames: `assets/${gitHash}/[name].[hash].js`,
        chunkFileNames: `assets/${gitHash}/[name].[hash].js`,
        assetFileNames: (_assetInfo) => {
          return `assets/${gitHash}/[name].[hash][extname]`;
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

// Export a function to access Vite params
export default ({ command, mode }) => {
  // Example: log the command and mode
  console.log('Vite command:', command); // 'serve' or 'build'
  console.log('Vite mode:', mode); // e.g., 'development' or 'production'
  // Example: check for custom CLI flags
  if (process.argv.includes('--my-custom-flag')) {
    console.log('Custom flag detected!');
  }
  return viteConfig;
};
