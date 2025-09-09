const child_process = require('child_process');
const fs = require('fs');
const path = require('path');
const minimist = require('minimist');
const { createFileHashesMain } = require('./cfh.cjs');

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

/**
 * Runs a real git merge with the given remote and branch, checks for conflicts, and aborts the merge.
 * Returns an object with hasConflict (boolean) and conflictFiles (array of "both modified" files).
 *
 * WARNING: This will modify the repo state and may require cleanup (merge --abort) after running.
 *
 * @param {string} [remote='origin'] - The remote name to merge from (e.g., 'origin').
 * @param {string} [branch='master'] - The branch name to merge from (e.g., 'master').
 * @returns {{ hasConflict: boolean, conflictFiles: string[] }}
 *   hasConflict: true if any conflicts were detected, false otherwise.
 *   conflictFiles: array of conflicted file paths ("both modified"), or an empty array if no conflicts.
 */
function getConflictFiles(remote = 'origin', branch = 'master') {
  const mergeArgs = ['--no-commit', '--no-ff'];
  const remoteBranch = `${remote}/${branch}`;

  const mergeResult = child_process.spawnSync('git', ['merge', ...mergeArgs, remoteBranch], { encoding: 'utf8' });
  if (mergeResult.error) {
    console.error('Failed to run git merge:', mergeResult.error.message);
    process.exit(1);
  }

  // Check for conflicts in merge output
  let hasConflict = false;
  if (/CONFLICT/i.test(mergeResult.stdout) || /CONFLICT/i.test(mergeResult.stderr)) {
    hasConflict = true;
  }

  // Always check git status for conflict files
  const statusResult = child_process.spawnSync('git', ['status'], { encoding: 'utf8' });
  if (statusResult.error) {
    console.error('Failed to run git status:', statusResult.error.message);
    process.exit(1);
  }

  const output = statusResult.stdout || '';
  const lines = output.split(/\r?\n/);

  const conflictFiles = [];
  let inUnmerged = false;
  for (const line of lines) {
    if (/Unmerged paths:/.test(line)) {
      inUnmerged = true;
      continue;
    }
    if (inUnmerged) {
      // End of unmerged section
      if (/^\s*$/.test(line) || /^[A-Z]/.test(line)) {
        inUnmerged = false;
        continue;
      }
      // Look for 'both modified:'
      const match = line.match(/both modified:\s+(.+)/);
      if (match) {
        conflictFiles.push(match[1].trim());
      }
    }
  }

  // Abort merge
  const abortResult = child_process.spawnSync('git', ['merge', '--abort'], { encoding: 'utf8' });
  if (abortResult.error) {
    console.error('Failed to abort merge:', abortResult.error.message);
    process.exit(1);
  }

  return {
    hasConflict,
    conflictFiles
  };
}

// Parse CLI arguments using minimist
const args = minimist(process.argv.slice(2));
// Support: --remote, --branch, or positional [remote] [branch]
const remoteName = args.remote || args.r || args._[0] || 'origin';
const branchName =
  args.branch ||
  args.b ||
  args._[1] ||
  child_process.execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' }).trim();

async function gitPullMain() {
  // Detect if git pull will have conflict before running it
  try {
    // Fetch latest changes
    const fetchResult = child_process.spawnSync('git', ['fetch', remoteName, branchName], { stdio: 'inherit' });
    if (fetchResult.status !== 0) {
      console.error('Git fetch failed.');
      process.exit(1);
    }

    // Check for conflicts
    const { conflictFiles } = getConflictFiles(remoteName, branchName);
    console.log('Conflict files:', conflictFiles);

    // Run git pull
    const pullResult = child_process.spawnSync('git', ['pull', remoteName, branchName], { stdio: 'inherit' });
    if (pullResult.status === 0) {
      console.log('Git pull completed successfully.');
    } else {
      console.error('Git pull failed.');
    }

    const isHashTxtConflict = conflictFiles.length === 1 && conflictFiles[0] === '.husky/hash.txt';

    if (isHashTxtConflict) {
      console.warn('Only .husky/hash.txt has conflict. Truncating and staging .husky/hash.txt...');
      try {
        await createFileHashesMain();
        child_process.execSync('git add .husky/hash.txt');
        child_process.execSync('git merge --continue');
        console.log('.husky/hash.txt conflict resolved by truncating and staging.');
      } catch (e) {
        console.error('Failed to resolve .husky/hash.txt conflict:', e.message);
        try {
          child_process.execSync('git merge --abort');
        } catch {
          // ignore
        }
        process.exit(1);
      }
    } else if (conflictFiles.length > 0) {
      console.error('Merge conflict detected! Aborting git pull.');
      // Abort the merge if it started (already done in getConflictFiles)
      process.exit(1);
    } else {
      // No conflict detected, continue as normal
      console.log('No merge conflicts detected. Proceeding with git pull.');
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

    if (process.platform === 'linux') {
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
  }

  // Execute fix-perm script
  try {
    child_process.execSync(`bash "${path.join(CWD, 'bin', 'fix-perm')}"`, { stdio: 'inherit' });
  } catch (e) {
    console.warn('Error executing fix-perm', e.message);
  }
}

gitPullMain().catch((err) => {
  console.error('Error occurred during git pull:', err.message);
  process.exit(1);
});
