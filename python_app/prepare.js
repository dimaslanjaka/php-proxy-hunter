import path from 'path';
import { BUILD_DIR, TEMP_DIR } from './config.js';
import cpy from 'cpy';

export async function prepareBuild() {
  // Copying necessary data files to temp directory
  const copyMap = [
    { src: 'assets/database', dest: `${TEMP_DIR}/assets/database` },
    // { src: 'assets/chrome', dest: `${TEMP_DIR}/assets/chrome` },
    // { src: 'assets/chrome-extensions', dest: `${TEMP_DIR}/assets/chrome-extensions` },
    { src: 'js', dest: `${TEMP_DIR}/js` },
    { src: 'favicon.ico', dest: `${TEMP_DIR}/favicon.ico` },
    { src: 'src/**/*.{mmdb,py}', dest: `${TEMP_DIR}/src` },
    { src: 'data/**/*.{pem,py}', dest: `${TEMP_DIR}/data` }
  ];
  for (const { src, dest } of copyMap) {
    console.log(`Copying ${src} to ${dest}...`);
    await cpy(src, dest);
  }
}

export async function copyAssets() {
  const copyMap = [{ src: 'src/**/*.mmdb', dest: path.join(BUILD_DIR, 'src') }];

  for (const { src, dest } of copyMap) {
    console.log(`Copying ${src} to ${dest}...`);
    await cpy(src, dest);
  }
}

if (process.argv.some((arg) => arg.includes('prepare.js'))) {
  copyAssets().catch(console.error);
}
