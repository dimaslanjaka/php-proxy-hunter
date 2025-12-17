import react from '@vitejs/plugin-react';
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vite';
import mkcert from 'vite-plugin-mkcert';
import {
  customStaticAssetsPlugin,
  fontsResolverPlugin,
  indexHtmlReplacementPlugin,
  manualHmrPlugin,
  prepareVitePlugins,
  TailwindCSSBuildPlugin
} from './vite-plugin.js';
import { execSync } from 'child_process';
import legacy from '@vitejs/plugin-legacy';
import browserslist from 'browserslist';
import { browserslistToTargets } from 'lightningcss';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Output directory for React build
const distPath = path.resolve(__dirname, 'dist/react');
// Get current git commit hash for versioning
const gitCommitHash = execSync('git rev-parse --short HEAD').toString().trim();

// Load .env file (dotenv)
const sampleCfg = dotenv.config({ path: path.resolve(__dirname, '.env.example') });
const dotCfg = dotenv.config({ override: true });
const isGithubCI = process.env.GITHUB_ACTIONS === 'true';

/** Prepare VITE_ prefixed env variables for define
 * @type {Record<string, string>}
 */
const viteEnv = {
  'import.meta.env.VITE_GIT_COMMIT': JSON.stringify(gitCommitHash),
  VITE_GIT_COMMIT: JSON.stringify(gitCommitHash)
};
if (isGithubCI) {
  // In GitHub CI, ensure all variables from .env.example are set, using defaults if necessary
  for (const [key, _value] of Object.entries(sampleCfg.parsed || {})) {
    const deviceValue = process.env[key];
    if (deviceValue) {
      if (key.startsWith('VITE_')) {
        viteEnv[`import.meta.env.${key}`] = JSON.stringify(deviceValue);
        viteEnv[key] = JSON.stringify(deviceValue);
      } else {
        viteEnv[key] = JSON.stringify(deviceValue);
        viteEnv[`import.meta.env.VITE_${key}`] = JSON.stringify(deviceValue);
        process.env[`VITE_${key}`] = deviceValue; // ensure process.env.VITE_ variables are set
      }
    }
  }
}
// Loop through loaded .env variables
for (const [key, value] of Object.entries(dotCfg.parsed || {})) {
  if (key.startsWith('VITE_')) {
    viteEnv[`import.meta.env.${key}`] = JSON.stringify(value);
    viteEnv[key] = JSON.stringify(value);
  } else {
    // Also expose non-VITE_ variables without import.meta.env. prefix
    viteEnv[key] = JSON.stringify(value);
    viteEnv[`import.meta.env.VITE_${key}`] = JSON.stringify(value);
    process.env[`VITE_${key}`] = value; // ensure process.env.VITE_ variables are set
  }
}

export const viteConfig = defineConfig({
  root: '.',
  // Inject all VITE_ env variables and git commit hash
  define: viteEnv,
  // Use a custom cache directory for Vite
  cacheDir: path.resolve(__dirname, 'tmp/.vite'),
  // Register Vite plugins
  plugins: [
    prepareVitePlugins(),
    manualHmrPlugin(),
    indexHtmlReplacementPlugin(),
    fontsResolverPlugin(),
    TailwindCSSBuildPlugin(),
    react(),
    mkcert(),
    customStaticAssetsPlugin()
  ],
  // Module resolution settings
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
      '/assets/fonts': path.resolve(__dirname, 'assets/fonts')
    },
    extensions: ['.mjs', '.js', '.mts', '.ts', '.jsx', '.tsx', '.json', '.cjs']
  },
  // CSS transformer and targets
  css: {
    transformer: 'lightningcss',
    lightningcss: {
      targets: browserslistToTargets(browserslist('>= 0.25%'))
    }
  },
  // Optimize dependencies
  optimizeDeps: {
    include: ['react', 'react-dom', 'react-router-dom', 'lodash', 'moment']
  },
  // Build configuration
  build: {
    outDir: distPath,
    commonjsOptions: {
      transformMixedEsModules: true,
      // Include node_modules and all .cjs files under src so Vite's
      // CommonJS transformer picks them up automatically.
      include: [/node_modules/, /src\/.*\.cjs$/]
    },
    sourcemap: false,
    // watch: {
    //   include: ['src/**/*.cjs', 'src/**/*.jsx', 'src/**/*.js', 'src/**/*.mjs', 'src/**/*.tsx', 'src/**/*.ts'],
    //   exclude: [
    //     '**/node_modules/**',
    //     '**/dist/**',
    //     '**/build/**',
    //     '**/coverage/**',
    //     '**/packages/**',
    //     '**/tmp/**',
    //     '**/transpile/**',
    //     '**/docs/**',
    //     '**/.yarn/**',
    //     '**/.cache/**',
    //     '**/.vscode/**',
    //     '**/.idea/**',
    //     '**/.git/**',
    //     '**/.github/**',
    //     '**/.husky/**',
    //     '**/public/**',
    //     '**/tests/**',
    //     '**/test/**',
    //     '**/.deploy_git/**'
    //   ]
    // },
    // Clean the output directory before each build
    emptyOutDir: true,
    minify: 'terser',
    // Remove all comments from minified JS and CSS
    terserOptions: {
      format: {
        comments: false
      }
    },
    cssMinify: 'lightningcss', // ensure CSS is minified
    cssCodeSplit: true,
    // For full CSS comment removal, use cssnano via PostCSS if needed
    rollupOptions: {
      // https://rollupjs.org/configuration-options/
      maxParallelFileOps: 2,
      output: {
        manualChunks: (id) => {
          if (id.includes('node_modules')) {
            if (id.startsWith('react')) {
              if (id.includes('router')) {
                return 'react-router';
              }
              return 'react';
            }
            if (id.startsWith('@mui/')) {
              return 'mui';
            }
            if (id.includes('moment')) {
              return 'moment';
            }
            if (id.startsWith('nik')) {
              return 'nik';
            }
            if (/tailwind|flowbite|bootstrap|popperjs/.test(id)) {
              return 'ui-lib';
            }
            if (/highlight|prism/.test(id)) {
              return 'syntax-highlighter';
            }
            if (/proxy|proxies/.test(id)) {
              return 'proxy';
            }
            // Other vendor libraries
            return 'vendor';
          }
          if (id.includes('components')) {
            return 'components';
          }
          if (/pages?/.test(id)) {
            return 'pages';
          }
          if (/helpers?|utils/.test(id)) {
            return 'utils-helpers';
          }
          // Let Rollup handle other modules automatically by returning undefined
        },
        entryFileNames: `assets/[name].[hash].js`,
        chunkFileNames: `assets/[name].[hash].js`,
        assetFileNames: (assetInfo) => {
          // Group assets by type: fonts, json, images, css, other
          /** @type {string[]} */
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
  // Dev server configuration
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
        '**/public/**',
        '**/tests/**',
        '**/test/**',
        '**/.deploy_git/**',
        'src/**/*.js',
        'src/**/*.jsx',
        'src/**/*.ts',
        'src/**/*.mjs',
        'src/**/*.cjs',
        '**/*.txt',
        '**/*.py',
        '**/*.php',
        'packages/**'
      ],
      // enable polling is slower but reliable
      usePolling: false,
      // interval for polling in ms
      interval: 3000
    }
  }
});

if (!isGithubCI && viteConfig.plugins) {
  viteConfig.plugins.push(
    legacy({
      targets: ['defaults', 'not IE 11']
    })
  );
}

export default viteConfig;
