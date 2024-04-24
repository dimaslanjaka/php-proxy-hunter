import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';

puppeteer
  .use(StealthPlugin())
  .launch({ headless: false })
  .then(async (browser) => {
    const page = await browser.newPage();
    await page.goto('https://bot.sannysoft.com');
    await page.waitForNavigation({
      waitUntil: 'networkidle0'
    });
    await page.screenshot({ path: 'screenshoots/stealth.png', fullPage: true });
    // await browser.close();
  });
