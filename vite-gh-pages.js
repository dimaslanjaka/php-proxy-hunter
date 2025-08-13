import * as cheerio from 'cheerio';
import { spawnSync } from 'child_process';
import fs from 'fs-extra';
import path from 'upath';
import { fileURLToPath } from 'url';
import { build, mergeConfig } from 'vite';
import routes from './src/react/routes.json' with { type: 'json' };
import config from './vite.config.js';

/**
 * Fixes __dirname for ESM modules.
 * @type {string}
 */
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Vite configuration merged with GitHub Pages base path.
 * @type {import('vite').UserConfig}
 */
const viteConfig = mergeConfig(config, { base: '/php-proxy-hunter/' });

/**
 * Builds the project for GitHub Pages and deploys it.
 * @returns {Promise<void>}
 */
async function buildForGithubPages() {
  await build(viteConfig)
    .then(() => {
      console.log('Build successful for GitHub Pages');
    })
    .catch(console.error);
  // add .no_jekyll file to prevent Jekyll processing
  const noJekyllPath = path.join(viteConfig.build.outDir, '.no_jekyll');
  if (!fs.existsSync(noJekyllPath)) {
    fs.writeFileSync(noJekyllPath, '');
    console.log('.no_jekyll file created to prevent Jekyll processing');
  }
  // Deploy to .deploy_git directory
  await deploy();
}

/**
 * Deploys the built project to the .deploy_git directory for GitHub Pages.
 * Clones the repository if the directory does not exist.
 * Removes old assets and PHP files, then copies new build output.
 * @returns {Promise<void>}
 */
async function deploy() {
  // Ensure .deploy_git directory exists
  const deployGitPath = path.join(__dirname, '.deploy_git');
  if (!fs.existsSync(deployGitPath)) {
    const gitUrlResult = spawnSync('git', ['config', '--get', 'remote.origin.url'], {
      cwd: __dirname,
      encoding: 'utf-8'
    });
    const gitUrl = gitUrlResult.stdout.trim();
    console.log(`Cloning repository from ${gitUrl} to ${deployGitPath}`);
    spawnSync('git', ['clone', gitUrl, deployGitPath], {
      cwd: __dirname,
      stdio: 'inherit'
    });
  }
  // Delete react auto generated files
  fs.rmSync(path.join(deployGitPath, 'assets'), { recursive: true, force: true });
  // Copy dist/react to .deploy_git directory
  fs.copySync(viteConfig.build.outDir, deployGitPath, { overwrite: true, dereference: true });
  // Delete php assets
  fs.rmSync(path.join(deployGitPath, 'php'), { recursive: true, force: true });
  // Add cache busting to all html files
  const indexHtml = path.join(viteConfig.build.outDir, 'index.html');
  const relIndexHtml = path.relative(process.cwd(), indexHtml);
  // Modify html
  const htmlContent = fs.readFileSync(indexHtml, 'utf-8');
  const $ = cheerio.load(htmlContent);
  // Get latest git commit hash
  let version = 'unknown';
  try {
    const gitResult = spawnSync('git', ['rev-parse', '--short', 'HEAD'], { encoding: 'utf-8' });
    if (gitResult.status === 0 && gitResult.stdout) {
      version = gitResult.stdout.trim();
    }
  } catch {
    // ignore
  }
  // Process script and link tags for cache busting
  $('script[src], link[href]').each((_, el) => {
    const tag = el.tagName.toLowerCase();
    const src = $(el).attr('src') || $(el).attr('href');
    if (!src || src.startsWith('{{') || /^(#|data:|mailto:|tel:|javascript:|blob:|file:|https?:\/\/|\/\/)/.test(src)) {
      return;
    }
    try {
      const parseSrc = new URL(src, 'http://www.webmanajemen.com/php-proxy-hunter/');
      parseSrc.searchParams.set('version', version);
      const newUrl = parseSrc.pathname + parseSrc.search;
      if (tag === 'script') {
        $(el).attr('src', newUrl);
      } else if (tag === 'link') {
        $(el).attr('href', newUrl);
      }
      console.log(`Cache bust: ${tag.toUpperCase()} ${src} => ${newUrl}`);
    } catch {
      console.warn(`Failed to parse URL for cache busting: ${src}`);
    }
  });
  // Write modified HTML back to file
  fs.writeFileSync(indexHtml, $.html());
  console.log(`Updated ${relIndexHtml} with version query parameters.`);

  // Copy dist/react/index.html to spesific routes
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
    fs.copyFileSync(indexHtml, routeHtml);
    console.log(`Copied ${relIndexHtml} to ${relRouteHtml} after build.`);
  }
}

// Run the build and deploy process
buildForGithubPages();
