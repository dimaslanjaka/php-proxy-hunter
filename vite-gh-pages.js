import { build, mergeConfig } from 'vite';
import config from './vite.config.js';

async function buildForGithubPages() {
  const mergedConfig = mergeConfig(config, { base: '/php-proxy-hunter/' });
  await build(mergedConfig)
    .then(() => {
      console.log('Build successful for GitHub Pages');
    })
    .catch(console.error);
}

buildForGithubPages();
