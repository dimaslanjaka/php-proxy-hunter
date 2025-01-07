const puppeteer = require('puppeteer');
const _ = require('lodash');

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

const waitTillHTMLRendered = async (page, timeout = 30000) => {
  const checkDurationMsecs = 1000;
  const maxChecks = timeout / checkDurationMsecs;
  let lastHTMLSize = 0;
  let checkCounts = 1;
  let countStableSizeIterations = 0;
  const minStableSizeIterations = 3;

  while (checkCounts++ <= maxChecks) {
    let html = await page.content();
    let currentHTMLSize = html.length;

    // let bodyHTMLSize = await page.evaluate(() => document.body.innerHTML.length);

    // console.log('last: ', lastHTMLSize, ' <> curr: ', currentHTMLSize, ' body html size: ', bodyHTMLSize);

    if (lastHTMLSize != 0 && currentHTMLSize == lastHTMLSize) countStableSizeIterations++;
    else countStableSizeIterations = 0; //reset the counter

    if (countStableSizeIterations >= minStableSizeIterations) {
      // console.log('Page rendered fully..');
      break;
    }

    lastHTMLSize = currentHTMLSize;

    // Use setTimeout as a workaround for waitForTimeout or waitFor
    await new Promise((resolve) => setTimeout(resolve, checkDurationMsecs));
  }
};

const visited = new Set();
const unvisited = new Set();

async function crawl(url) {
  if (visited.has(url)) return crawl(_.sample(Array.from(unvisited)));
  visited.add(url);

  const browser = await puppeteer.launch({
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', // Windows path
    // headless: false, // Set headless to false to show the browser window
    defaultViewport: null // Optional: ensures the browser window is not constrained to a fixed size
    // args: ['--start-maximized'] // Optional: to start the browser maximized
  });
  const page = await browser.newPage();

  await page.goto(url, {
    timeout: 0 // Disable timeout completely
  });

  await waitTillHTMLRendered(page, 30000);
  await sleep(5000);

  const links = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('a'))
      .map((anchor) => anchor.href)
      .filter((href) => href.startsWith(window.location.origin) || href.startsWith('/'));
  });

  console.log(`Crawled ${url}`);
  // console.log(links);
  unvisited.add(...links);

  // Close the browser before recalling crawl
  await browser.close();

  // Recursively crawl each link
  for (const link of links) {
    await crawl(link);
  }

  await browser.close();
}

crawl('https://sh.webmanajemen.com:8443/proxy/')
  .then(() => console.log('Crawling completed'))
  .catch((err) => console.error(err));
