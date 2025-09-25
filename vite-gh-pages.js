import * as cheerio from 'cheerio';
import { spawnSync } from 'child_process';
import fs from 'fs-extra';
import path from 'upath';
import { fileURLToPath } from 'url';
import { build } from 'vite';
import routes from './src/react/routes.json' with { type: 'json' };
import viteConfig from './vite-gh-pages.config.js';
import { copyIndexHtml } from './vite-plugin.js';

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
  // Copy index.dev.html to index.html
  copyIndexHtml();
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

  // Verify the build output
  const indexHtml = path.join(viteConfig.build.outDir, 'index.html');
  if (!fs.existsSync(indexHtml)) {
    throw new Error(`Build failed: ${indexHtml} does not exist.`);
  }
  await deploy();
}

const deployGitPath = path.join(__dirname, '.deploy_git');

/**
 * Deploys the built project to the .deploy_git directory for GitHub Pages.
 * Clones the repository if the directory does not exist.
 * Removes old assets and PHP files, then copies new build output.
 * @returns {Promise<void>}
 */
async function deploy() {
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
  if (fs.existsSync(source)) {
    fs.copySync(source, target, { overwrite: true, dereference: true });
    console.log(
      `Copied assets from ${path.relative(process.cwd(), source)} to ${path.relative(process.cwd(), target)}`
    );
  } else {
    console.warn(`Source assets directory does not exist: ${path.relative(process.cwd(), source)}`);
  }

  // Cache busting for index.html
  const indexHtml = path.join(viteConfig.build.outDir, 'index.html');
  cacheBustHtml(indexHtml);

  // Copy index.html to each route in .deploy_git
  await copyIndexToRoutes();
}

/**
 * Adds cache-busting version query parameters to script and link tags in an HTML file.
 * @param {string} htmlFilePath - Path to the HTML file to update.
 * @param {string} [version] - Optional version string. If not provided, uses current git commit hash.
 */
export function cacheBustHtml(htmlFilePath, version) {
  if (!fs.existsSync(htmlFilePath)) {
    console.warn(`File does not exist for cache busting: ${htmlFilePath}`);
    return;
  }
  let $ = cheerio.load(fs.readFileSync(htmlFilePath, 'utf-8'));
  let ver = version || 'unknown';
  if (!version) {
    try {
      const gitResult = spawnSync('git', ['rev-parse', '--short', 'HEAD'], { encoding: 'utf-8' });
      if (gitResult.status === 0 && gitResult.stdout) {
        ver = gitResult.stdout.trim();
      }
    } catch {
      // ignore
    }
  }
  $('script[src], link[href]').each((_, el) => {
    const tag = el.tagName.toLowerCase();
    const src = $(el).attr('src') || $(el).attr('href');
    if (!src || src.startsWith('{{') || /^(#|data:|mailto:|tel:|javascript:|blob:|file:|https?:\/\/|\/\/)/.test(src))
      return;
    try {
      const parseSrc = new URL(src, 'http://www.webmanajemen.com/php-proxy-hunter/');
      parseSrc.searchParams.set('version', ver);
      const newUrl = parseSrc.pathname + parseSrc.search;
      if (tag === 'script') $(el).attr('src', newUrl);
      else if (tag === 'link') $(el).attr('href', newUrl);
      console.log(`Cache bust: ${tag.toUpperCase()} ${src} => ${newUrl}`);
    } catch {
      console.warn(`Failed to parse URL for cache busting: ${src}`);
    }
  });
  const relHtml = path.relative(process.cwd(), htmlFilePath);
  fs.writeFileSync(htmlFilePath, $.html());
  $ = null; // Help GC
  console.log(`Updated ${relHtml} with version query parameters.`);
}

/**
 * Copies the built index.html file to each route defined in routes.json, ensuring
 * each route is served its own index.html for proper SPA routing on GitHub Pages.
 *
 * For each route in routes.json, this function determines the correct output path
 * (e.g., /about/index.html) and copies the built index.html to that location in the
 * deployment directory. Handles both string and array route paths.
 *
 * @param {string} [sourceHtml] - Optional path to the source index.html file to copy.
 *   Defaults to the built index.html in the Vite output directory.
 * @param {string} [targetDir] - Optional target directory to use as the root for copying index.html files.
 *   If not provided, defaults to the deployGitPath directory.
 * @returns {Promise<void>} Resolves when all index.html files have been copied for all routes.
 */
export async function copyIndexToRoutes(sourceHtml = undefined, targetDir = undefined) {
  if (!sourceHtml) sourceHtml = path.join(viteConfig.build.outDir, 'index.html');
  const relIndexHtml = path.relative(process.cwd(), sourceHtml);
  // Read the HTML content once for reuse in each route
  const htmlContent = fs.readFileSync(sourceHtml, 'utf-8');

  for (const routeOrig of routes) {
    let route = { ...routeOrig };
    // Support string or string[] for path
    const paths = Array.isArray(route.path) ? route.path : [route.path];
    for (let pathItem of paths) {
      let routePath = pathItem;
      // If routePath ends with a slash, treat as directory and append index.html
      if (routePath.endsWith('/')) {
        routePath += 'index.html';
      } else if (!routePath.endsWith('.html')) {
        // If not ending with .html, treat as directory and append index.html
        routePath += '/index.html';
      }
      const routePathWithoutHtml = routePath.replace(/\.html$/, '');
      const routeHtml = path.join(targetDir || deployGitPath, `${routePathWithoutHtml}.html`);
      const relRouteHtml = path.relative(process.cwd(), routeHtml);
      fs.ensureDirSync(path.dirname(routeHtml));
      if (!fs.existsSync(sourceHtml)) {
        // Source HTML file must exist for copying
        console.error(`Source HTML does not exist: ${path.relative(process.cwd(), sourceHtml)}`);
        continue;
      }
      // Use a local Cheerio instance for each route to avoid memory leaks
      let $ = cheerio.load(htmlContent);
      // Modify HTML title for the route if provided
      if (route.title) {
        // Set <title> tag
        $('title').text(route.title);
        // Update or create meta[property="og:title"]
        let ogTitleTag = $('meta[property="og:title"]');
        if (ogTitleTag.length === 0) {
          $('head').append('<meta property="og:title">');
          ogTitleTag = $('meta[property="og:title"]');
        }
        ogTitleTag.attr('content', route.title);
        // Update or create meta[name="twitter:title"]
        let twitterTitleTag = $('meta[name="twitter:title"]');
        if (twitterTitleTag.length === 0) {
          $('head').append('<meta name="twitter:title">');
          twitterTitleTag = $('meta[name="twitter:title"]');
        }
        twitterTitleTag.attr('content', route.title);
        // Log update for debugging
        console.log(`Updated title, og:title, and twitter:title in ${relIndexHtml} for route ${routePath}`);
      }
      // Modify meta description if provided
      if (route.description) {
        // Update or create meta[name="description"]
        let descTag = $('meta[name="description"]');
        if (descTag.length === 0) {
          $('head').append('<meta name="description">');
          descTag = $('meta[name="description"]');
        }
        descTag.attr('content', route.description);
        // Update or create meta[property="og:description"]
        let ogDescTag = $('meta[property="og:description"]');
        if (ogDescTag.length === 0) {
          $('head').append('<meta property="og:description">');
          ogDescTag = $('meta[property="og:description"]');
        }
        ogDescTag.attr('content', route.description);
        // Update or create meta[name="twitter:description"]
        let twitterDescTag = $('meta[name="twitter:description"]');
        if (twitterDescTag.length === 0) {
          $('head').append('<meta name="twitter:description">');
          twitterDescTag = $('meta[name="twitter:description"]');
        }
        twitterDescTag.attr('content', route.description);
        // Log update for debugging
        console.log(
          `Updated meta description, og:description, and twitter:description in ${relIndexHtml} for route ${routePath}`
        );
      }
      // Modify meta thumbnail if provided
      if (route.thumbnail) {
        // Update or create meta[property="og:image"]
        let ogImageTag = $('meta[property="og:image"]');
        if (ogImageTag.length === 0) {
          $('head').append('<meta property="og:image">');
          ogImageTag = $('meta[property="og:image"]');
        }
        ogImageTag.attr('content', route.thumbnail);
        // Update or create meta[name="twitter:image"]
        let twitterImageTag = $('meta[name="twitter:image"]');
        if (twitterImageTag.length === 0) {
          $('head').append('<meta name="twitter:image">');
          twitterImageTag = $('meta[name="twitter:image"]');
        }
        twitterImageTag.attr('content', route.thumbnail);
        // Update or create meta[name="image"]
        let imageTag = $('meta[name="image"]');
        if (imageTag.length === 0) {
          $('head').append('<meta name="image">');
          imageTag = $('meta[name="image"]');
        }
        imageTag.attr('content', route.thumbnail);
        // Log update for debugging
        console.log(`Updated meta og:image, twitter:image, and image in ${relIndexHtml} for route ${routePath}`);
      }
      // Modify meta canonical if provided
      if (route.canonical) {
        // Update or create link[rel="canonical"]
        let canonicalTag = $('link[rel="canonical"]');
        if (canonicalTag.length === 0) {
          $('head').append('<link rel="canonical">');
          canonicalTag = $('link[rel="canonical"]');
        }
        canonicalTag.attr('href', route.canonical);
        // Update or create meta[property="og:url"]
        let ogUrlTag = $('meta[property="og:url"]');
        if (ogUrlTag.length === 0) {
          $('head').append('<meta property="og:url">');
          ogUrlTag = $('meta[property="og:url"]');
        }
        ogUrlTag.attr('content', route.canonical);
        // Update or create meta[name="twitter:url"]
        let twitterUrlTag = $('meta[name="twitter:url"]');
        if (twitterUrlTag.length === 0) {
          $('head').append('<meta name="twitter:url">');
          twitterUrlTag = $('meta[name="twitter:url"]');
        }
        twitterUrlTag.attr('content', route.canonical);
        // Log update for debugging
        console.log(`Updated link rel="canonical" in ${relIndexHtml} for route ${routePath}`);
      }
      try {
        // Write the modified HTML to the route's HTML file
        fs.writeFileSync(routeHtml, $.html());
        $ = null; // Help GC
        if (!fs.existsSync(routeHtml)) {
          // Check if file was written successfully
          console.error(`Failed to copy to: ${relRouteHtml}`);
        } else {
          // Log successful copy
          console.log(`Copied ${relIndexHtml} to ${relRouteHtml} after build.`);
        }
      } catch (err) {
        // Log any error during file write
        console.error(`Error copying ${relIndexHtml} to ${relRouteHtml}:`, err);
      }
    }
  }
}

// Run the build and deploy process
if (process.argv.some((arg) => arg.includes('vite-gh-pages.js'))) {
  buildForGithubPages().catch(console.error);
}
