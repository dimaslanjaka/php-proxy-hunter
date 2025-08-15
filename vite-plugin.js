import { spawnSync } from 'child_process';
import fs from 'fs-extra';
import path from 'upath';
import { buildTailwind } from './tailwind.build.js';

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
    async configResolved() {
      try {
        buildTailwind();
      } catch (error) {
        console.error('Failed to build Tailwind CSS:', error);
      }
    }
  };
}

/**
 * Vite plugin to replace the index.html with index.dev.html for certain routes during dev server.
 * @returns {import('vite').Plugin}
 */
export function indexHtmlReplacementPlugin() {
  let viteConfig;
  return {
    name: 'index-html-replacement',
    configResolved(config) {
      viteConfig = config;
      if (config.command === 'serve') {
        // Copy index.dev.html to index.html for development mode.
        // Do not remove: ensures dev server uses index.dev.html content as index.html.
        // In production, index.html is generated in dist/react and index.dev.html is ignored.
        const devHtml = path.join(process.cwd(), 'index.dev.html');
        const prodHtml = path.join(process.cwd(), 'index.html');
        fs.copyFileSync(devHtml, prodHtml);
      }

      // Execute git history builder
      spawnSync(
        'node',
        [
          '--no-warnings=ExperimentalWarning',
          '--loader',
          'ts-node/esm',
          path.join(process.cwd(), 'src/dev/git-history.builder.ts')
        ],
        { stdio: 'inherit', shell: true }
      );
    },
    closeBundle() {
      // Copy compiled index.html to the project directory for production.
      const src = path.join(process.cwd(), 'dist/react/index.html');
      const dest = path.join(process.cwd(), 'index.html');
      if (fs.existsSync(src) && fs.statSync(src).size > 0) {
        fs.copySync(src, dest, { overwrite: true });
      } else {
        console.warn(`Source file "${src}" does not exist or is empty. Skipping copy.`);
      }
    }
    // /**
    //  * Configures the dev server to serve index.dev.html for specific routes.
    //  * @param {import('vite').ViteDevServer} server
    //  */
    // configureServer(server) {
    //   // Replace index.html with index.dev.html for specific routes
    //   server.middlewares.use((req, _res, next) => {
    //     const devRoutes = [
    //       '/',
    //       '/index.html',
    //       '/outbound',
    //       '/login',
    //       '/oauth',
    //       '/about',
    //       '/settings',
    //       '/dashboard',
    //       '/logout',
    //       '/proxy',
    //       '/contact'
    //     ];
    //     if (devRoutes.includes(req.url)) {
    //       req.url = '/index.dev.html';
    //     }
    //     next();
    //   });
    // }
  };
}
