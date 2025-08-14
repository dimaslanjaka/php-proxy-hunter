import * as cheerio from 'cheerio';
import { spawnSync } from 'child_process';
import fs from 'fs-extra';
import path from 'upath';
import { fileURLToPath } from 'url';
import { build } from 'vite';
import routes from './src/react/routes.json' with { type: 'json' };
import viteConfig from './vite-gh-pages.config.js';

// Fixes __dirname for ESM modules
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Builds the project for GitHub Pages and deploys it.
 * @returns {Promise<void>}
 */
async function buildForGithubPages() {
  // Clean the output directory
  if (fs.existsSync(viteConfig.build.outDir)) {
    fs.rmSync(viteConfig.build.outDir, { recursive: true, force: true });
    console.log(`Cleaned output directory: ${viteConfig.build.outDir}`);
  }
  // Build the project
  try {
    await build(viteConfig);
    console.log('Build successful for GitHub Pages');
  } catch (err) {
    console.error('Build failed:', err);
    return;
  }
  const noJekyllPath = path.join(viteConfig.build.outDir, '.no_jekyll');
  if (!fs.existsSync(noJekyllPath)) {
    fs.writeFileSync(noJekyllPath, '');
    console.log('.no_jekyll file created to prevent Jekyll processing');
  }
  await deploy();
}

/**
 * Deploys the built project to the .deploy_git directory for GitHub Pages.
 * Clones the repository if the directory does not exist.
 * Removes old assets and PHP files, then copies new build output.
 * @returns {Promise<void>}
 */
async function deploy() {
  const deployGitPath = path.join(__dirname, '.deploy_git');
  // Ensure .deploy_git exists and is up to date
  if (!fs.existsSync(deployGitPath)) {
    const gitUrl = spawnSync('git', ['config', '--get', 'remote.origin.url'], {
      cwd: __dirname,
      encoding: 'utf-8'
    }).stdout.trim();
    console.log(`Cloning repository from ${gitUrl} to ${deployGitPath}`);
    spawnSync('git', ['clone', gitUrl, deployGitPath], { cwd: __dirname, stdio: 'inherit' });
  } else {
    console.log(`Fetching latest changes in ${deployGitPath}`);
    spawnSync('git', ['fetch', 'origin'], { cwd: deployGitPath, stdio: 'inherit' });
    console.log(`Resetting repository in ${deployGitPath} to origin/gh-pages`);
    spawnSync('git', ['reset', '--hard', 'origin/gh-pages'], { cwd: deployGitPath, stdio: 'inherit' });
  }

  // Copy build output to .deploy_git
  fs.copySync(viteConfig.build.outDir, deployGitPath, { overwrite: true, dereference: true });
  console.log(`Copied build output to ${deployGitPath}`);

  // Clean and copy build output
  for (const dir of ['assets', 'php', 'static']) {
    const target = path.join(deployGitPath, dir);
    if (fs.existsSync(target)) {
      fs.rmSync(target, { recursive: true, force: true });
      console.log(`Removed old directory: ${target}`);
    }
  }

  // Re-copy build assets
  const source = path.join(viteConfig.build.outDir, 'assets');
  const target = path.join(deployGitPath, 'assets');
  fs.copySync(source, target, { overwrite: true, dereference: true });
  console.log(`Copied assets from ${path.relative(process.cwd(), source)} to ${path.relative(process.cwd(), target)}`);

  // Cache busting for index.html
  const indexHtml = path.join(viteConfig.build.outDir, 'index.html');
  const $ = cheerio.load(fs.readFileSync(indexHtml, 'utf-8'));
  let version = 'unknown';
  try {
    const gitResult = spawnSync('git', ['rev-parse', '--short', 'HEAD'], { encoding: 'utf-8' });
    if (gitResult.status === 0 && gitResult.stdout) {
      version = gitResult.stdout.trim();
    }
  } catch {
    // ignore
  }
  $('script[src], link[href]').each((_, el) => {
    const tag = el.tagName.toLowerCase();
    const src = $(el).attr('src') || $(el).attr('href');
    if (!src || src.startsWith('{{') || /^(#|data:|mailto:|tel:|javascript:|blob:|file:|https?:\/\/|\/\/)/.test(src))
      return;
    try {
      const parseSrc = new URL(src, 'http://www.webmanajemen.com/php-proxy-hunter/');
      parseSrc.searchParams.set('version', version);
      const newUrl = parseSrc.pathname + parseSrc.search;
      if (tag === 'script') $(el).attr('src', newUrl);
      else if (tag === 'link') $(el).attr('href', newUrl);
      console.log(`Cache bust: ${tag.toUpperCase()} ${src} => ${newUrl}`);
    } catch {
      console.warn(`Failed to parse URL for cache busting: ${src}`);
    }
  });
  const relIndexHtml = path.relative(process.cwd(), indexHtml);
  fs.writeFileSync(indexHtml, $.html());
  console.log(`Updated ${relIndexHtml} with version query parameters.`);

  // Copy index.html to each route in .deploy_git
  for (const routeOrig of routes) {
    let route = { ...routeOrig };
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
    const routeHtml = path.join(deployGitPath, `${routePathWithoutHtml}.html`);
    fs.ensureDirSync(path.dirname(routeHtml));
    if (!fs.existsSync(indexHtml)) {
      console.error(`Source HTML does not exist: ${path.relative(process.cwd(), indexHtml)}`);
      continue;
    }
    const relRouteHtml = path.relative(process.cwd(), routeHtml);
    try {
      fs.copySync(indexHtml, routeHtml, { overwrite: true, dereference: true });
      if (!fs.existsSync(routeHtml)) {
        console.error(`Failed to copy to: ${relRouteHtml}`);
      } else {
        console.log(`Copied ${relIndexHtml} to ${relRouteHtml} after build.`);
      }
    } catch (err) {
      console.error(`Error copying ${relIndexHtml} to ${relRouteHtml}:`, err);
    }
  }
}

// Run the build and deploy process
buildForGithubPages().catch(console.error);
