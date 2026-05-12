import fs from 'fs';
import path from 'path';
import puppeteer from 'puppeteer';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

(async () => {
  // download tampermonkey zip from https://chrome-stats.com/d/dhdgffkkebhmkfjojejmpbldmpobfkfo/download-thank?type=ZIP&version=5.5.0&versionCode=5.5.0#google_vignette
  const extensionPath = path.resolve(process.cwd(), '.cache', 'tampermonkey');
  const userDataDir = path.resolve(process.cwd(), 'tmp/profiles/profile1');
  const userScriptPath = path.join(process.cwd(), 'userscripts', 'universal.user.js');

  console.log('Extension path:', extensionPath);
  console.log('User data dir:', userDataDir);
  console.log('User script path:', userScriptPath);

  if (!fs.existsSync(userDataDir)) {
    fs.mkdirSync(userDataDir, { recursive: true });
  }

  const browser = await puppeteer.launch({
    channel: 'chrome',
    headless: false,
    enableExtensions: true,
    executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    pipe: true,
    defaultViewport: null,
    userDataDir,
    devtools: true,
    args: [
      '--start-maximized',
      `--disable-extensions-except=${extensionPath}`,
      `--load-extension=${extensionPath}`,
      '--no-first-run',
      '--no-default-browser-check',
      '--disable-dev-shm-usage',
      '--disable-session-crashed-bubble',
      '--disable-infobars',
      '--disable-features=Translate,BackForwardCache'
    ]
  });

  const page = await browser.newPage();
  // await page.setViewport({
  //   width: 1920,
  //   height: 1080,
  //   deviceScaleFactor: 1
  // });

  const script = fs.readFileSync(userScriptPath, 'utf-8');
  await page.evaluateOnNewDocument(script);
  await page.goto('https://spys.one/en/');
  // keep browser open
  await new Promise(() => {});
})();
