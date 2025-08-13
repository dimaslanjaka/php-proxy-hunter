import { spawnSync } from 'child_process';
import fs from 'fs-extra';

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
        const result = spawnSync(
          'npx',
          ['--yes', '@tailwindcss/cli@latest', '-i', './tailwind.input.css', '-o', './src/react/components/theme.css'],
          {
            stdio: 'inherit',
            shell: true
          }
        );
        if (result.error || result.status !== 0) {
          throw result.error || new Error(`Tailwind CSS build failed with exit code ${result.status}`);
        }
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
  return {
    name: 'index-html-replacement',
    /**
     * Configures the dev server to serve index.dev.html for specific routes.
     * @param {import('vite').ViteDevServer} server
     */
    configureServer(server) {
      // Write index.html from index.dev.html for development
      fs.copyFileSync('index.dev.html', 'index.html');
      // Replace index.html with index.dev.html for specific routes
      server.middlewares.use((req, _res, next) => {
        const devRoutes = [
          '/',
          '/index.html',
          '/outbound',
          '/login',
          '/oauth',
          '/about',
          '/settings',
          '/dashboard',
          '/logout',
          '/proxy'
        ];
        if (devRoutes.includes(req.url)) {
          req.url = '/index.dev.html';
        }
        next();
      });
    }
  };
}
