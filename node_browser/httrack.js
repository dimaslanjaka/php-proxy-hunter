import fs from 'fs/promises';
import path from 'path';
import { URL } from 'url';
import { fetch } from 'undici';
import { load } from 'cheerio';

function usage() {
  console.log('Usage: node node_browser/httrack.js <startUrl> [-o outdir] [-c concurrency] [--same-origin]');
  console.log('If <startUrl> is omitted the script uses the default or $HTTRACK_START environment variable.');
  process.exit(1);
}

const rawArgs = process.argv.slice(2);
const DEFAULT_START = 'https://fontawesome.com/icons/filter?f=duotone&s=du';

let startUrl;
let outDir = path.join('tmp', 'mirror');
let concurrency = 6;
let sameOriginOnly = false;

// determine whether the first arg is a URL or an option
let argIndex = 0;
if (rawArgs[0] && !rawArgs[0].startsWith('-')) {
  startUrl = rawArgs[0];
  argIndex = 1;
} else {
  startUrl = process.env.HTTRACK_START || DEFAULT_START;
  argIndex = 0;
}

if (rawArgs.includes('-h') || rawArgs.includes('--help')) usage();

for (let i = argIndex; i < rawArgs.length; i++) {
  const a = rawArgs[i];
  if (a === '-o' && rawArgs[i + 1]) {
    outDir = rawArgs[++i];
    continue;
  }
  if (a === '-c' && rawArgs[i + 1]) {
    concurrency = Number(rawArgs[++i]) || concurrency;
    continue;
  }
  if (a === '--same-origin') {
    sameOriginOnly = true;
    continue;
  }
}

const startBase = new URL(startUrl);

const visited = new Set();
const queue = [];

function enqueue(u, isPage = false) {
  try {
    const resolved = new URL(u, startBase).toString();
    if (visited.has(resolved)) return;
    if (sameOriginOnly && new URL(resolved).origin !== startBase.origin) return;
    visited.add(resolved);
    queue.push({ url: resolved, isPage });
  } catch (_) {
    // ignore
  }
}

function sanitizeFilename(p) {
  // remove query/hash
  return p.split(/[?#]/)[0];
}

function urlToLocalPath(u) {
  const parsed = new URL(u);
  let pathname = sanitizeFilename(parsed.pathname);
  if (!pathname || pathname === '/') pathname = '/index.html';
  // if no extension and looks like a page, append .html
  if (!path.extname(pathname)) {
    if (pathname.endsWith('/')) pathname += 'index.html';
    else pathname += '.html';
  }
  const full = path.join(outDir, parsed.hostname, decodeURIComponent(pathname));
  return full;
}

async function saveBuffer(filePath, buffer) {
  await fs.mkdir(path.dirname(filePath), { recursive: true });
  await fs.writeFile(filePath, buffer);
}

async function appendSavedUrl(u) {
  try {
    const listFile = path.join(outDir, 'downloaded_urls.txt');
    await fs.appendFile(listFile, u + '\n');
  } catch (_) {
    // ignore logging errors
  }
}

async function downloadResource(task) {
  const { url, isPage } = task;
  try {
    const res = await fetch(url);
    if (!res.ok) {
      console.warn('Skipped (status)', res.status, url);
      return;
    }
    if (isPage) {
      const text = await res.text();
      const $ = load(text, { decodeEntities: false });

      const attrs = [
        ['img', 'src'],
        ['script', 'src'],
        ['link', 'href'],
        ['source', 'src'],
        ['video', 'src'],
        ['audio', 'src']
      ];
      for (const [sel, attr] of attrs) {
        $(sel).each((i, el) => {
          const val = $(el).attr(attr);
          if (!val) return;
          try {
            const absolute = new URL(val, url).toString();
            enqueue(absolute, sel === 'link' ? false : false);
            const local = path
              .relative(path.dirname(urlToLocalPath(url)), urlToLocalPath(absolute))
              .split(path.sep)
              .join('/');
            $(el).attr(attr, local);
          } catch (_) {}
        });
      }

      // rewrite anchor links to local HTML for same-origin pages
      $('a[href]').each((i, el) => {
        const val = $(el).attr('href');
        if (!val) return;
        try {
          const absolute = new URL(val, url).toString();
          if (new URL(absolute).origin === startBase.origin) {
            enqueue(absolute, true);
            const local = path
              .relative(path.dirname(urlToLocalPath(url)), urlToLocalPath(absolute))
              .split(path.sep)
              .join('/');
            $(el).attr('href', local);
          }
        } catch (_) {}
      });

      const outPath = urlToLocalPath(url);
      await saveBuffer(outPath, Buffer.from($.html(), 'utf8'));
      await appendSavedUrl(url);
      console.log('Saved page:', outPath);
    } else {
      const contentType = res.headers && res.headers.get ? res.headers.get('content-type') || '' : '';
      const outPath = urlToLocalPath(url);
      if (contentType.includes('text/css') || url.toLowerCase().endsWith('.css')) {
        // treat as text CSS, extract url(...) references (fonts, images)
        const cssText = await res.text();
        await saveBuffer(outPath, Buffer.from(cssText, 'utf8'));
        // extract URLs from CSS and enqueue them
        extractUrlsFromCss(cssText, url).forEach((u) => enqueue(u, false));
        await appendSavedUrl(url);
        console.log('Saved CSS:', outPath);
      } else {
        // binary asset
        const ab = await res.arrayBuffer();
        await saveBuffer(outPath, Buffer.from(ab));
        await appendSavedUrl(url);
        console.log('Saved asset:', outPath);
      }
    }
  } catch (err) {
    console.warn('Error downloading', url, err.message);
  }
}

function extractUrlsFromCss(cssText, baseUrl) {
  const urls = [];
  const re = /url\(([^)]+)\)/g;
  let m;
  while ((m = re.exec(cssText)) !== null) {
    let raw = m[1].trim();
    if ((raw.startsWith('"') && raw.endsWith('"')) || (raw.startsWith("'") && raw.endsWith("'"))) {
      raw = raw.slice(1, -1);
    }
    if (!raw || raw.startsWith('data:')) continue;
    try {
      const absolute = new URL(raw, baseUrl).toString();
      urls.push(absolute);
    } catch (_) {}
  }
  return urls;
}

async function worker() {
  while (true) {
    const task = queue.shift();
    if (!task) break;
    await downloadResource(task);
  }
}

async function run() {
  enqueue(startUrl, true);
  await fs.mkdir(outDir, { recursive: true });
  const workers = [];
  for (let i = 0; i < concurrency; i++) workers.push(worker());
  await Promise.all(workers);
  console.log('Done. Files written to', outDir);
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
