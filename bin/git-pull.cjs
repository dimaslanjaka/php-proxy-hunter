#!/usr/bin/env node

const child_process = require('child_process');
const fs = require('fs');
const path = require('path');

// Get script directory and repo root
const SCRIPT_DIR = __dirname;
const CWD = path.dirname(SCRIPT_DIR);

// Change to repo root
try {
  process.chdir(CWD);
} catch (_err) {
  console.error('Repository path not found');
  process.exit(1);
}

// Run `git pull` with passed arguments
const pullResult = child_process.spawnSync('git', ['pull', ...process.argv.slice(2)], { stdio: 'inherit' });
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
  const shFiles = fs.readdirSync(CWD).filter((f) => f.endsWith('.sh'));
  for (let i = 0; i < shFiles.length; i++) {
    const file = shFiles[i];
    try {
      fs.chmodSync(file, 0o755);
    } catch (_err) {
      console.warn(`Failed to chmod ${file}`, _err.message);
    }
  }

  // Execute fix-*.sh scripts
  for (let i = 0; i < shFiles.length; i++) {
    const file = shFiles[i];
    if (file.startsWith('fix-')) {
      try {
        child_process.execSync(`bash "${file}"`, { stdio: 'inherit' });
      } catch (_err) {
        console.warn(`Error executing ${file}`, _err.message);
      }
    }
  }
}

// Handle .husky/hash.txt changes
try {
  const status = child_process.execSync('git status --porcelain .husky/hash.txt', { encoding: 'utf8' });
  if (/^( M|M |A |\?\?)/.test(status)) {
    console.log('Detected changes in .husky/hash.txt');
    fs.writeFileSync('.husky/hash.txt', '');
    child_process.execSync('git add .husky/hash.txt');
    console.log('.husky/hash.txt has been truncated and staged.');
    child_process.execSync('git checkout -- .husky/hash.txt', { stdio: 'inherit' });
    console.log('.husky/hash.txt has been reset to the last committed state.');
  } else {
    console.log('.husky/hash.txt is clean. No action needed.');
  }
} catch (_err) {
  console.warn('Could not check or update .husky/hash.txt', _err.message);
}

// Execute fix-perm script
try {
  child_process.execSync(`bash "${path.join(CWD, 'bin', 'fix-perm')}"`, { stdio: 'inherit' });
} catch (_err) {
  console.warn('Error executing fix-perm', _err.message);
}
