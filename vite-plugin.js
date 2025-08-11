import { spawnSync } from 'child_process';

export function TailwindCSSBuildPlugin() {
  return {
    name: 'tailwindcss-build-plugin',
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

export function indexHtmlReplacementPlugin() {
  return {
    name: 'index-html-replacement',
    // transformIndexHtml: {
    //   order: 'pre',
    //   handler(_html, _ctx) {
    //     const content = fs.readFileSync(path.join(__dirname, 'index.dev.html'), 'utf-8');
    //     return {
    //       html: content,
    //       tags: []
    //     };
    //   }
    // },
    configureServer(server) {
      server.middlewares.use((req, _res, next) => {
        if (req.url === '/') {
          req.url = '/index.dev.html';
        }
        next();
      });
    }
  };
}
