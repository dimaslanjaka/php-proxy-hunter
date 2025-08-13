import { spawnSync } from 'child_process';
import fs from 'fs-extra';
import path from 'upath';
import routes from './src/react/routes.json' with { type: 'json' };

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
        const inputCss = path.join(process.cwd(), 'tailwind.input.css');
        const outputCss = path.join(process.cwd(), 'src/react/components/theme.css');
        const result = spawnSync('npx', ['--yes', '@tailwindcss/cli@latest', '-i', inputCss, '-o', outputCss], {
          stdio: 'inherit',
          shell: true
        });
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
      const devHtml = path.join(process.cwd(), 'index.dev.html');
      const prodHtml = path.join(process.cwd(), 'index.html');
      fs.copyFileSync(devHtml, prodHtml);
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
          '/proxy',
          '/contact'
        ];
        if (devRoutes.includes(req.url)) {
          req.url = '/index.dev.html';
        }
        next();
      });
    },
    /**
     * After build hook to copy index.dev.html to index.html.
     * This ensures that the production build uses the correct HTML file.
     */
    closeBundle() {
      const devHtml = path.join(process.cwd(), 'dist/react/index.dev.html');
      // Copy dist/react/index.dev.html to dist/react/index.html after build
      const prodHtml = path.join(process.cwd(), 'dist/react/index.html');
      fs.copyFileSync(devHtml, prodHtml);
      const relDevHtml = path.relative(process.cwd(), devHtml);
      const relProdHtml = path.relative(process.cwd(), prodHtml);
      console.log(`Copied ${relDevHtml} to ${relProdHtml} after build.`);
      // Copy dist/react/index.dev.html to spesific routes
      for (const route of routes) {
        if (route.path.endsWith('/')) {
          // Ensure the route does not end with a slash
          route.path += 'index.html';
        } else if (!route.path.endsWith('.html')) {
          // Append index.html if it does not end with .html
          // This ensures that the route is treated as a directory
          // and served as index.html
          // e.g., /about becomes /about/index.html
          route.path += '/index.html';
        }
        const routePathWithoutHtml = route.path.replace(/\.html$/, '');
        const routeHtml = path.join(process.cwd(), 'dist/react', `${routePathWithoutHtml}.html`);
        const relRouteHtml = path.relative(process.cwd(), routeHtml);
        fs.ensureDirSync(path.dirname(routeHtml)); // Ensure the directory exists
        fs.copyFileSync(devHtml, routeHtml);
        console.log(`Copied ${relDevHtml} to ${relRouteHtml} after build.`);
      }
    }
  };
}
