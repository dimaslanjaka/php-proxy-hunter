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

// Detect if git pull will have conflict before running it
try {
  // Fetch latest changes
  const fetchResult = child_process.spawnSync('git', ['fetch', ...process.argv.slice(2)], { stdio: 'inherit' });
  if (fetchResult.status !== 0) {
    console.error('Git fetch failed.');
    process.exit(1);
  }

  // Get current branch name
  const branchName = child_process.execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' }).trim();
  const remoteName = 'origin';
  const remoteBranch = `${remoteName}/${branchName}`;

  // Dry-run merge to detect conflicts
  let mergeResult;
  try {
    mergeResult = child_process.spawnSync(
      'git',
      ['merge', '--no-commit', '--no-ff', '--no-edit', '--dry-run', remoteBranch],
      { encoding: 'utf8' }
    );
  } catch (err) {
    console.error('Error running git merge dry-run:', err.message);
    process.exit(1);
  }

  const mergeOutput = (mergeResult.stderr || '') + (mergeResult.stdout || '');
  if (/CONFLICT/i.test(mergeOutput)) {
    // Check if the only conflict is .husky/hash.txt
    /** @type {string[]} */
    let conflictFiles = mergeOutput.match(/CONFLICT \(content\): Merge conflict in (.+)/g) || [];
    conflictFiles = conflictFiles.map(function (line) {
      return line.replace(/.*Merge conflict in /, '').trim();
    });
    if (conflictFiles.length === 1 && conflictFiles[0] === '.husky/hash.txt') {
      console.warn('Only .husky/hash.txt has conflict. Truncating and staging .husky/hash.txt...');
      try {
        fs.writeFileSync('.husky/hash.txt', '');
        child_process.execSync('git add .husky/hash.txt');
        child_process.execSync('git merge --continue');
        console.log('.husky/hash.txt conflict resolved by truncating and staging.');
      } catch (e) {
        console.error('Failed to resolve .husky/hash.txt conflict:', e.message);
        try {
          child_process.execSync('git merge --abort');
        } catch (_) {
          // ignore
        }
        process.exit(1);
      }
    } else {
      console.error('Merge conflict detected! Aborting git pull.');
      // Abort the merge if it started
      try {
        child_process.execSync('git merge --abort');
      } catch (_) {
        // ignore
      }
      process.exit(1);
    }
  } else {
    // No conflict detected, continue as normal
    console.log('No merge conflicts detected in dry-run. Proceeding with git pull.');
  }

  // If no conflict, reset any changes from dry-run
  try {
    child_process.execSync('git reset --hard');
  } catch (_) {
    // ignore
  }

  // Now run git pull
  const pullResult = child_process.spawnSync('git', ['pull', ...process.argv.slice(2)], { stdio: 'inherit' });
  if (pullResult.status === 0) {
    console.log('Git pull completed successfully.');
  } else {
    console.error('Git pull failed.');
  }
} catch (err) {
  console.error('Error during git pull conflict check:', err.message);
  process.exit(1);
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
