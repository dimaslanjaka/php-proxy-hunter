import { spawnSync } from 'child_process';
import fs from 'fs-extra';
import path from 'upath';
import { fileURLToPath } from 'url';
import { buildTailwind } from './tailwind.build.js';
import { copyIndexToRoutes, generateSitemapTxt, generateSitemapXml } from './vite-gh-pages.js';

// Fixes __dirname for ESM modules
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Equivalent of __dirname for ESM modules. Returns the directory of the current module file.
 * @returns
 */
function get__dirname() {
  return path.dirname(fileURLToPath(import.meta.url));
}

/**
 * Vite plugin to manually trigger HMR updates via HTTP endpoint.
 * Allows programmatic/manual triggering of file change detection.
 * This works by actually touching/modifying the file to trigger the file watcher.
 * @returns {import('vite').Plugin}
 */
export function manualHmrPlugin() {
  /** @type {import('vite').ViteDevServer} */
  let server;
  return {
    name: 'manual-hmr-trigger',
    configureServer(viteServer) {
      server = viteServer;

      viteServer.middlewares.use('/api/trigger-hmr', (req, res) => {
        try {
          // Trigger full reload for all modules
          console.log(`[Manual HMR] Full reload triggered`);

          server.ws.send({
            type: 'full-reload'
          });

          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ success: true, message: 'Full reload triggered' }));
          console.log(`[Manual HMR] Successfully triggered full reload`);
        } catch (error) {
          console.error(`[Manual HMR] Error:`, error);
          res.writeHead(500, { 'Content-Type': 'application/json' });
          const errorMessage = error instanceof Error ? error.message : String(error);
          res.end(JSON.stringify({ success: false, message: `Error: ${errorMessage}` }));
        }
      });
    }
  };
}

/**
 * Vite plugin to build Tailwind CSS using the Tailwind CLI.
 * Runs the build process during Vite's config resolution.
 * @returns {import('vite').Plugin}
 */
export function TailwindCSSBuildPlugin() {
  return {
    name: 'tailwindcss-build-plugin',
    /**
     * Runs Tailwind CSS build when Vite config is resolved.
     * @returns {Promise<void>}
     */
    async configResolved(_config) {
      try {
        buildTailwind();
      } catch (error) {
        console.error('Failed to build Tailwind CSS:', error);
      }
    },
    /**
     * Use Vite's watcher to rebuild Tailwind CSS when tailwind.input.css changes.
     */
    handleHotUpdate({ file, server }) {
      const tailwindInputPath = path.join(__dirname, 'tailwind.input.css');
      if (file === tailwindInputPath) {
        try {
          buildTailwind();
          server.ws.send({ type: 'full-reload' });
          console.log('Rebuilt Tailwind CSS and triggered full reload due to tailwind.input.css change.');
        } catch (error) {
          console.error('Failed to rebuild Tailwind CSS on change:', error);
        }
      }
    }
  };
}

/**
 * Copies index.dev.html to index.html for development mode.
 * Ensures the dev server uses index.dev.html content as index.html.
 * In production, index.html is generated in dist/react and index.dev.html is ignored.
 */
export function copyIndexHtml() {
  // Copy index.dev.html to index.html for development mode.
  // Use a safe base directory in case __dirname is not defined when bundled by Vite.
  // Do not remove: ensures dev server uses index.dev.html content as index.html.
  // In production, index.html is generated in dist/react and index.dev.html is ignored.
  const baseDir = typeof __dirname === 'string' && __dirname ? __dirname : path.resolve(process.cwd());
  const devHtml = path.join(baseDir, 'index.dev.html');
  const prodHtml = path.join(baseDir, 'index.html');
  try {
    if (!fs.existsSync(devHtml)) {
      console.warn(`copyIndexHtml: source file not found, skipping: ${devHtml}`);
      return;
    }
    fs.copyFileSync(devHtml, prodHtml);
    console.log(
      `copyIndexHtml: copied ${path.relative(process.cwd(), devHtml)} to ${path.relative(process.cwd(), prodHtml)}`
    );
  } catch (err) {
    console.error('copyIndexHtml failed:', err);
  }
}

/**
 * Vite plugin to replace the index.html with index.dev.html for certain routes during dev server.
 * @returns {import('vite').Plugin}
 */
export function indexHtmlReplacementPlugin() {
  /** @type {import('vite').ResolvedConfig} */
  let viteConfig;
  return {
    name: 'index-html-replacement',
    configResolved(config) {
      viteConfig = config;
      copyIndexHtml();

      // Execute git history builder
      spawnSync(
        'node',
        [
          '--no-warnings=ExperimentalWarning',
          '--loader',
          'ts-node/esm',
          path.join(__dirname, 'src/dev/git-history.builder.ts')
        ],
        { stdio: 'inherit', shell: true }
      );
    },
    async closeBundle() {
      if (viteConfig.command === 'build') {
        await copyIndexForProduction();
        generateSitemapTxt(__dirname);
        generateSitemapXml(__dirname);
      }
    }
  };
}

export async function copyIndexForProduction() {
  // Optimized: Copy compiled index.html to the project directory for production only if it exists and is non-empty.
  const src = path.join(__dirname, 'dist/react/index.html');
  const dest = path.join(__dirname, 'index.html');
  try {
    if (!fs.existsSync(src)) throw new Error(`Source file "${src}" does not exist. Skipping copy.`);
    const stat = fs.statSync(src);
    if (stat.size === 0) throw new Error(`Source file "${src}" is empty. Skipping copy.`);
    fs.copySync(src, dest, { overwrite: true });
    console.log(`Copied ${src} to ${dest}`);
    // Copy index.html to all route destinations in dist/react
    await copyIndexToRoutes(src, path.join(__dirname, 'dist/react'));
  } catch (err) {
    const errorMessage = err instanceof Error ? err.message : String(err);
    console.warn(errorMessage);
  }
}

/**
 * Vite plugin to serve fonts from the /assets/fonts directory during development.
 * @returns {import('vite').Plugin}
 */
export function fontsResolverPlugin() {
  return {
    name: 'fonts-resolver',
    /**
     * Configures the dev server to serve fonts from the /assets/fonts directory.
     * @param {import('vite').ViteDevServer} server - The Vite dev server instance.
     */
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        // Serve fonts from the /assets/fonts directory
        if (req.url && req.url.startsWith('/assets/fonts/')) {
          // Decode URI to handle spaces and special characters in filenames
          const fontFile = decodeURIComponent(req.url.replace('/assets/fonts/', ''));
          const fontPath = path.join(__dirname, 'assets/fonts', fontFile);
          if (fs.existsSync(fontPath)) {
            // Detect MIME type based on file extension
            const ext = path.extname(fontPath).toLowerCase();
            let mimeType = 'application/octet-stream';
            if (ext === '.woff2') mimeType = 'font/woff2';
            else if (ext === '.woff') mimeType = 'font/woff';
            else if (ext === '.ttf') mimeType = 'font/ttf';
            else if (ext === '.otf') mimeType = 'font/otf';
            else if (ext === '.eot') mimeType = 'application/vnd.ms-fontobject';
            else if (ext === '.svg') mimeType = 'image/svg+xml';
            else if (ext === '.css') mimeType = 'text/css';
            else if (ext === '.js') mimeType = 'application/javascript';
            res.setHeader('Content-Type', mimeType);
            fs.createReadStream(fontPath).pipe(res);
          } else {
            res.statusCode = 404;
            res.end('Font not found');
          }
        } else {
          next();
        }
      });
    }
  };
}

/**
 * Vite plugin to serve custom static assets from /assets files during development.
 * @returns {import('vite').Plugin}
 */
export function customStaticAssetsPlugin() {
  /** @type {import('vite').ResolvedConfig} */
  let config;
  return {
    name: 'custom-static-assets',
    configResolved(_config) {
      config = _config;
    },
    closeBundle() {
      // Skip non-build command
      if (config.command !== 'build') return;

      /** @type {string[]} */
      const filesToCopy = [];
      for (const file of filesToCopy) {
        const srcPath = path.join(__dirname, file);
        const destPaths = [path.join(__dirname, 'public', file), path.join(config.build.outDir, file)];
        for (const destPath of destPaths) {
          if (fs.existsSync(srcPath)) {
            fs.ensureDirSync(path.dirname(destPath));
            fs.copySync(srcPath, destPath, { overwrite: true, dereference: true });
            console.log(`Copied ${path.relative(__dirname, srcPath)} to ${path.relative(__dirname, destPath)}`);
          } else {
            console.warn(`Source file "${srcPath}" does not exist. Skipping copy.`);
          }
        }
      }

      // Copy robots.txt to the project directory for production.
      const robotsSrc = path.join(__dirname, 'robots.txt');
      const destinations = [path.join(__dirname, 'dist/react/robots.txt'), path.join(__dirname, 'public/robots.txt')];

      if (fs.existsSync(robotsSrc)) {
        const stat = fs.statSync(robotsSrc);
        if (stat.size > 0) {
          for (const dest of destinations) {
            fs.copySync(robotsSrc, dest, { overwrite: true });
          }
          console.log(`Copied ${robotsSrc} to: ${destinations.join(', ')}`);
        } else {
          console.warn(`Source file "${robotsSrc}" is empty. Skipping copy.`);
        }
      }
    }
  };
}

/**
 * Simple plugin to copy all fonts to `public/assets/fonts` and ensure
 * `dist/react/assets/fonts` exists before the build starts. This prevents
 * Rollup/Vite ENOENT failures when emitting fingerprinted font assets.
 * @returns {import('vite').Plugin}
 */
export function copyFontsPlugin() {
  /** @type {import('vite').ResolvedConfig} */
  let viteConfig;
  return {
    name: 'copy-fonts-plugin',
    configResolved(config) {
      viteConfig = config;
    },
    buildStart() {
      try {
        const srcDir = path.join(__dirname, 'assets/fonts');
        const publicDest = path.join(__dirname, 'public', 'assets', 'fonts');
        const outDir =
          (viteConfig && viteConfig.build && viteConfig.build.outDir) || path.join(__dirname, 'dist', 'react');
        const distDest = path.join(outDir, 'assets', 'fonts');

        if (!fs.existsSync(srcDir)) {
          console.warn(`copy-fonts-plugin: source fonts directory not found: ${srcDir}`);
          return;
        }

        // Ensure destination directories exist
        fs.ensureDirSync(publicDest);
        fs.ensureDirSync(distDest);

        // Copy fonts to public (for static serving) and to dist output (defensive)
        fs.copySync(srcDir, publicDest, { overwrite: true, dereference: true });
        fs.copySync(srcDir, distDest, { overwrite: true, dereference: true });

        console.log(`copy-fonts-plugin: copied fonts -> public/assets/fonts and ${path.relative(__dirname, distDest)}`);
      } catch (err) {
        console.error('copy-fonts-plugin error:', err);
      }
    }
  };
}

/**
 * Vite plugin to serve pre-built assets from `dist/react/assets` during development.
 * This allows the dev server to resolve requests for fingerprinted files that
 * already exist in the built `dist/react/assets` directory.
 * @returns {import('vite').Plugin}
 */
export function serveDistAssetsPlugin() {
  const distBase = path.join(get__dirname(), 'dist/react');
  const allowedPrefixes = ['/assets/', '/data/', '/config/'];
  return {
    name: 'serve-dist-assets',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        try {
          if (!req.url) return next();
          const prefix = allowedPrefixes.find((p) => req.url.startsWith(p));
          if (!prefix) return next();
          const rel = decodeURIComponent(req.url.replace(new RegExp('^' + prefix), ''));
          const baseDirName = prefix.replace(/\//g, '');
          const filePath = path.join(distBase, baseDirName, rel);
          if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
            const ext = path.extname(filePath).toLowerCase();
            const mimeMap = {
              '.js': 'application/javascript',
              '.css': 'text/css',
              '.json': 'application/json',
              '.png': 'image/png',
              '.jpg': 'image/jpeg',
              '.jpeg': 'image/jpeg',
              '.svg': 'image/svg+xml',
              '.gif': 'image/gif',
              '.webp': 'image/webp',
              '.ico': 'image/x-icon',
              '.woff2': 'font/woff2',
              '.woff': 'font/woff',
              '.ttf': 'font/ttf',
              '.otf': 'font/otf',
              '.eot': 'application/vnd.ms-fontobject'
            };
            const mime = mimeMap[ext] || 'application/octet-stream';
            res.setHeader('Content-Type', mime);
            const stream = fs.createReadStream(filePath);
            stream.on('error', next);
            res.statusCode = 200;
            stream.pipe(res);
            return;
          }
          next();
        } catch (_) {
          next();
        }
      });
    }
  };
}

const lockFilePath = path.join(__dirname, 'tmp/locks/.dev-server-lock');
const buildLockFilePath = path.join(__dirname, 'tmp/locks/.build-lock');
fs.ensureDirSync(path.dirname(lockFilePath));
fs.ensureDirSync(path.dirname(buildLockFilePath));
// Track if running as dev server
let isDevServer = false;
// Track if running as build
let isBuild = false;

/** @returns {import('vite').Plugin} */
export function prepareVitePlugins() {
  return {
    name: 'prepare-vite-plugins',
    configResolved(config) {
      // Only set up lock file when conditions are met
      isDevServer = config.command === 'serve';
      isBuild = config.command === 'build';
      const lockContents = `${config.command}-start:${new Date().toISOString()} pid:${process.pid}\n`;
      if (isDevServer) {
        fs.writeFileSync(lockFilePath, lockContents);
      } else if (isBuild) {
        fs.writeFileSync(buildLockFilePath, lockContents);
      }
    },
    handleHotUpdate() {
      // Only update lock file if running dev server
      if (isDevServer) {
        fs.writeFileSync(lockFilePath, 'lock');
      }
    }
  };
}

// Register at exit handlers to remove lock file only if running dev server
process.on('exit', () => {
  if (isDevServer && fs.existsSync(lockFilePath)) {
    fs.removeSync(lockFilePath);
  }
  if (isBuild && fs.existsSync(buildLockFilePath)) {
    fs.removeSync(buildLockFilePath);
  }
});
process.on('SIGINT', () => {
  process.exit();
});
process.on('SIGTERM', () => {
  process.exit();
});
process.on('uncaughtException', (err) => {
  console.error('Uncaught Exception:', err);
  process.exit(1);
});

// If this script is run directly, copy index.html and build Tailwind CSS
// This ensures index.html is present and Tailwind CSS is built before starting dev server or building
if (process.argv.some((arg) => arg.includes('vite-plugin.js'))) {
  copyIndexHtml();
  buildTailwind();
  spawnSync(
    'node',
    [
      '--no-warnings=ExperimentalWarning',
      '--loader',
      'ts-node/esm',
      path.join(__dirname, 'src/dev/git-history.builder.ts')
    ],
    { stdio: 'inherit', shell: true }
  );
}
