#!/usr/bin/env node

const { spawnSync, execSync } = require('child_process');
const { chmodSync, writeFileSync, readdirSync } = require('fs');
const { join, dirname } = require('path');

// Get script directory and repo root
const SCRIPT_DIR = __dirname;
const CWD = dirname(SCRIPT_DIR);

// Change to repo root
try {
  process.chdir(CWD);
} catch (_err) {
  console.error('Repository path not found');
  process.exit(1);
}

// Run `git pull` with passed arguments
const pullResult = spawnSync('git', ['pull', ...process.argv.slice(2)], { stdio: 'inherit' });
if (pullResult.status === 0) {
  console.log('Git pull completed successfully.');
} else {
  console.error('Git pull failed.');
}

// Check if running under cron
if (process.env.CRON_TZ) {
  console.log('Script is running under crontab');
} else {
  console.log('Script is not running under crontab');

  // Make *.sh executable
  const shFiles = readdirSync(CWD).filter((f) => f.endsWith('.sh'));
  shFiles.forEach((file) => {
    try {
      chmodSync(file, 0o755);
    } catch (_err) {
      console.warn(`Failed to chmod ${file}`);
    }
  });

  // Execute fix-*.sh scripts
  shFiles
    .filter((f) => f.startsWith('fix-'))
    .forEach((file) => {
      try {
        execSync(`bash "${file}"`, { stdio: 'inherit' });
      } catch (_err) {
        console.warn(`Error executing ${file}`);
      }
    });
}

// Handle .husky/hash.txt changes
try {
  const status = execSync('git status --porcelain .husky/hash.txt', { encoding: 'utf8' });
  if (/^( M|M |A |\?\?)/.test(status)) {
    console.log('Detected changes in .husky/hash.txt');
    writeFileSync('.husky/hash.txt', '');
    execSync('git add .husky/hash.txt');
    console.log('.husky/hash.txt has been truncated and staged.');
  } else {
    console.log('.husky/hash.txt is clean. No action needed.');
  }
} catch (_err) {
  console.warn('Could not check or update .husky/hash.txt');
}

// Execute fix-perm script
try {
  execSync(`bash "${join(CWD, 'bin', 'fix-perm')}"`, { stdio: 'inherit' });
} catch (_err) {
  console.warn('Error executing fix-perm');
}
