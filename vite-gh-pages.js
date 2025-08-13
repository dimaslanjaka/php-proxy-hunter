import { build, mergeConfig } from 'vite';
import config from './vite.config.js';
import fs from 'fs-extra';
import path from 'upath';

async function buildForGithubPages() {
  const mergedConfig = mergeConfig(config, { base: '/php-proxy-hunter/' });
  await build(mergedConfig)
    .then(() => {
      console.log('Build successful for GitHub Pages');
    })
    .catch(console.error);
  // add .no_jekyll file to prevent Jekyll processing
  const noJekyllPath = path.join(mergedConfig.build.outDir, '.no_jekyll');
  if (!fs.existsSync(noJekyllPath)) {
    fs.writeFileSync(noJekyllPath, '');
    console.log('.no_jekyll file created to prevent Jekyll processing');
  }
}

buildForGithubPages();
