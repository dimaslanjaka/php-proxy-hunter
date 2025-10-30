import { spawnSync } from 'child_process';
import fs from 'fs-extra';
import path from 'upath';
import { fileURLToPath } from 'url';
import { buildTailwind } from './tailwind.build.js';
import { copyIndexToRoutes } from './vite-gh-pages.js';

// Fixes __dirname for ESM modules
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

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
  // Do not remove: ensures dev server uses index.dev.html content as index.html.
  // In production, index.html is generated in dist/react and index.dev.html is ignored.
  const devHtml = path.join(__dirname, 'index.dev.html');
  const prodHtml = path.join(__dirname, 'index.html');
  fs.copyFileSync(devHtml, prodHtml);
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
    console.warn(err.message || err);
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
        if (req.url.startsWith('/assets/fonts/')) {
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
 * Vite plugin to serve custom static assets from /assets and proxyManager.* files during development.
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

      const filesToCopy = ['proxyManager.html', 'proxyManager.js', 'proxyManager.css'];
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
