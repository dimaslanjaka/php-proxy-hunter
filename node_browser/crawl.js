import puppeteer from 'puppeteer';
import { writeFile } from 'fs/promises';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const TARGET = 'https://fontawesome.com/icons/filter?f=duotone&s=du';
const IGNORE_HOSTS = [
  'www.googletagmanager.com',
  'google.com',
  'google.co.id',
  'googlesyndication.com',
  'google-analytics.com',
  'gstatic.com',
  'facebook.com',
  'fbcdn.net'
];

function isIgnored(u) {
  try {
    const h = new URL(u).hostname;
    return IGNORE_HOSTS.some((host) => h === host || h.endsWith(`.${host}`));
  } catch (_) {
    return false;
  }
}

function isDataUri(u) {
  return typeof u === 'string' && u.startsWith('data:');
}

async function captureAssets(target = TARGET) {
  const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();

  const urls = new Set();
  const embedded = new Set();

  // capture network requests (dynamic assets, fonts, XHR, etc.)
  page.on('request', (req) => {
    const u = req.url();
    if (isIgnored(u) || isDataUri(u)) return;
    const type = req.resourceType();
    if (
      ['image', 'stylesheet', 'script', 'font', 'media', 'xhr', 'fetch'].includes(type) ||
      /\.(png|jpe?g|gif|svg|webp|css|js|woff2?|ttf|otf|mp4|webm|ogg)(\?|$)/i.test(u)
    ) {
      urls.add(u);
    }
  });

  // also capture responses (some resources may be fetched via service-worker or redirected)
  page.on('response', async (res) => {
    try {
      const u = res.url();
      if (isIgnored(u) || isDataUri(u)) return;
      const ct = (res.headers()['content-type'] || '').toLowerCase();

      // check CSS bodies for embedded woff2 data URIs
      if (ct.includes('css')) {
        try {
          const txt = await res.text();
          if (
            txt.includes('data:application/font-woff2') ||
            txt.includes('data:application/font-woff2;charset=utf-8')
          ) {
            embedded.add(u);
          }
          if (txt.includes('data:application/font-woff2') || /url\(data:application\/font-woff2/i.test(txt)) {
            urls.add(u);
          }
        } catch (_) {}
      }

      if (ct.includes('font') || ct.includes('image') || ct.includes('javascript')) {
        urls.add(u);
      }
    } catch (_) {}
  });

  await page.goto(target, { waitUntil: 'networkidle2', timeout: 60000 });

  // DOM-sweep for static links (img, script, link, source, video, audio, iframe, a)
  const domUrls = await page.evaluate(() => {
    const selectors = 'img[src], script[src], link[href], source[src], video[src], audio[src], iframe[src], a[href]';
    const nodes = Array.from(document.querySelectorAll(selectors));
    const out = [];
    nodes.forEach((n) => {
      ['src', 'href', 'srcset', 'data-src', 'data-srcset'].forEach((attr) => {
        const v = n.getAttribute && n.getAttribute(attr);
        if (!v) return;
        if (attr.includes('srcset')) {
          v.split(',').forEach((part) => {
            const url = part.trim().split(' ')[0];
            if (url) out.push(new URL(url, document.baseURI).href);
          });
        } else {
          try {
            out.push(new URL(v, document.baseURI).href);
          } catch (_) {}
        }
      });
    });
    return Array.from(new Set(out));
  });

  domUrls.forEach((u) => {
    if (!isIgnored(u) && !isDataUri(u)) urls.add(u);
  });

  // write results to file to avoid flooding the console
  const __filename = fileURLToPath(import.meta.url);
  const __dirname = dirname(__filename);
  const outPath = join(__dirname, 'assets.txt');
  let arr = Array.from(urls);
  arr = arr.filter((u) => !isIgnored(u) && !isDataUri(u));
  await writeFile(outPath, arr.join('\n'), 'utf8');
  // write embedded-font sources if any
  const embeddedPath = join(__dirname, 'embedded-fonts.txt');
  const embeddedArr = Array.from(embedded);
  if (embeddedArr.length) {
    await writeFile(embeddedPath, embeddedArr.join('\n'), 'utf8');
  }

  console.log(
    `Captured ${arr.length} asset URLs -> ${outPath}` +
      (embeddedArr.length ? `; ${embeddedArr.length} embedded-font sources -> ${embeddedPath}` : '')
  );

  await browser.close();
}

// run when invoked
captureAssets().catch((err) => {
  console.error('capture failed:', err);
  process.exitCode = 2;
});
