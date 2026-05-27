import react from '@vitejs/plugin-react';
import browserslist from 'browserslist';
import { execSync } from 'child_process';
import dotenv from 'dotenv';
import { browserslistToTargets } from 'lightningcss';
import moment from 'moment-timezone';
import path from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vite';
import mkcert from 'vite-plugin-mkcert';
import {
  copyFontsPlugin,
  serveDistAssetsPlugin,
  customStaticAssetsPlugin,
  fontsResolverPlugin,
  indexHtmlReplacementPlugin,
  manualHmrPlugin,
  prepareVitePlugins,
  TailwindCSSBuildPlugin
} from './vite-plugin.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Output directory for React build
const distPath = path.resolve(__dirname, 'dist/react');
// Get current git commit hash for versioning
const gitCommitHash = execSync('git rev-parse --short HEAD').toString().trim();

// Load .env file (dotenv)
const sampleCfg = dotenv.config({ path: path.resolve(__dirname, '.env.example'), override: false, quiet: true });
const dotCfg = dotenv.config({ override: true, quiet: true });
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
    // Serve pre-built assets from dist/react/assets when requested during dev
    serveDistAssetsPlugin(),
    prepareVitePlugins(),
    manualHmrPlugin(),
    indexHtmlReplacementPlugin(),
    fontsResolverPlugin(),
    // Register copyFontsPlugin only on GitHub Actions CI to avoid
    // serving local font assets in other environments.
    ...(isGithubCI ? [copyFontsPlugin()] : []),
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
    chunkSizeWarningLimit: 700,
    // For full CSS comment removal, use cssnano via PostCSS if needed
    rollupOptions: {
      // https://rollupjs.org/configuration-options/
      maxParallelFileOps: 2,
      output: {
        manualChunks: (id, _manualChunkMeta) => {
          if (id.includes('node_modules')) {
            let uniqueId = moment().format('YYYYMMDD-HHmmss');
            // Extract the package name (handles scoped packages)
            const pkgMatch = id.match(new RegExp('node_modules/(?:@[^/]+/[^/]+|[^/]+)'));
            const pkgPath = pkgMatch ? pkgMatch[0].replace('node_modules/', '') : null;
            if (pkgPath) {
              const pkgName = pkgPath.replace('@', '').replace('/', '-');

              // Core React packages - always in 'react' chunk
              if (pkgName.startsWith('react-dom') || pkgName === 'react' || pkgName === 'use-sync-external-store') {
                return 'react';
              }

              // Large, independent utility libraries (these don't depend on React)
              const independentLargePackages = {
                lodash: 'lodash',
                moment: 'moment',
                'chart.js': 'chart.js',
                'highlight.js': 'highlight.js',
                prismjs: 'prismjs',
                codemirror: 'codemirror'
              };

              uniqueId = moment().format('YYYYMMDD-HHmmss');

              for (const [pkgKey, chunkName] of Object.entries(independentLargePackages)) {
                if (pkgName.includes(pkgKey)) {
                  return uniqueId + '-' + chunkName + '-' + pkgName;
                }
              }

              // Everything else (React ecosystem + small packages) in vendor
              return uniqueId + '-' + 'vendor' + '-' + pkgName.replace('@', '').replace('/', '-');
            }
            return uniqueId + '-' + 'vendor' + '-' + id.split(new RegExp('node_modules/'))[1].split(/\W/).join('-');
          }
          // Let Rollup handle application code chunks
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

export default viteConfig;
