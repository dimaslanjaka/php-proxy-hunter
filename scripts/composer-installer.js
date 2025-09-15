// Script to download and install Composer (PHP dependency manager)

import https from 'https';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const composerUrl = 'https://getcomposer.org/composer-stable.phar';
const binDir = path.resolve(__dirname, '../bin');
const composerPath = path.join(binDir, 'composer.phar');

/**
 * Downloads a file from the given URL and saves it to the specified destination.
 *
 * @param {string} url - The URL to download the file from.
 * @param {string} dest - The destination file path where the downloaded file will be saved.
 * @param {(err: Error|null) => void} cb - Callback function called when download completes or fails.
 */
function downloadComposer(url, dest, cb) {
  const file = fs.createWriteStream(dest);
  https
    .get(url, (response) => {
      if (response.statusCode !== 200) {
        cb(new Error(`Failed to download composer: ${response.statusCode}`));
        return;
      }
      response.pipe(file);
      file.on('finish', () => {
        file.close(cb);
      });
    })
    .on('error', (err) => {
      fs.unlink(dest, () => cb(err));
    });
}

/**
 * Ensures that the bin directory exists. Creates it if it does not exist.
 */
function ensureBinDir() {
  if (!fs.existsSync(binDir)) {
    fs.mkdirSync(binDir, { recursive: true });
  }
}

/**
 * Makes the specified file executable (chmod 755).
 * On Windows, this operation is ignored if it fails.
 *
 * @param {string} filePath - The path to the file to make executable.
 */
function makeExecutable(filePath) {
  try {
    fs.chmodSync(filePath, 0o755);
  } catch (_) {
    // On Windows, chmod may fail, ignore
  }
}

/**
 * Main function to orchestrate downloading and installing Composer.
 * Ensures the bin directory exists, downloads Composer, makes it executable,
 * and creates a shortcut script for easier usage.
 */
function main() {
  ensureBinDir();
  console.log('Downloading Composer to', composerPath);
  downloadComposer(composerUrl, composerPath, (err) => {
    if (err) {
      console.error('Error downloading Composer:', err.message);
      process.exit(1);
    }
    makeExecutable(composerPath);
    console.log('Composer installed at', composerPath);
    // Optionally, create a shortcut script for easier usage
    const shortcut = path.join(binDir, process.platform === 'win32' ? 'composer.cmd' : 'composer');
    let shortcutContent;
    if (process.platform === 'win32') {
      shortcutContent = `@echo off\nphp "%~dp0composer.phar" %*`;
    } else {
      shortcutContent = `#!/bin/sh\nDIR=$(cd "$(dirname "$0")" && pwd)\nphp "$DIR/composer.phar" "$@"`;
    }
    fs.writeFileSync(shortcut, shortcutContent, { mode: 0o755 });
    console.log('Composer shortcut created at', shortcut);
  });
}

main();
