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

function ensureBinDir() {
  if (!fs.existsSync(binDir)) {
    fs.mkdirSync(binDir, { recursive: true });
  }
}

function makeExecutable(filePath) {
  try {
    fs.chmodSync(filePath, 0o755);
  } catch (_) {
    // On Windows, chmod may fail, ignore
  }
}

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
