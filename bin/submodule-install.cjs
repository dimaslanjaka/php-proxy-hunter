#!/usr/bin/env node

const { spawnSync } = require('child_process');
const { existsSync, rmSync } = require('fs');
const { join, resolve } = require('path');
const { config } = require('dotenv');

config(); // Load .env

// Parse CLI args
const args = process.argv.slice(2);
let ROOT = runGit(['rev-parse', '--show-toplevel']).trim();
let REPO_PATH = ROOT;

for (let i = 0; i < args.length; i++) {
  if (args[i] === '-cwd' && args[i + 1]) {
    ROOT = resolve(args[++i]);
  } else if (args[i].startsWith('--cwd=')) {
    ROOT = resolve(args[i].split('=')[1]);
  }
}

console.log(`Installing submodules at ${ROOT}`);

// Get submodule paths
const submoduleList = runGit([
  '-C', REPO_PATH,
  'config', '-f', '.gitmodules',
  '--get-regexp', '^submodule\\..*\\.path$'
])
  .split('\n')
  .filter(Boolean);

for (const line of submoduleList) {
  const [KEY, MODULE_PATH] = line.trim().split(/\s+/);
  const RELATIVE_MODULE_PATH = join(ROOT, MODULE_PATH);

  if (existsSync(RELATIVE_MODULE_PATH)) {
    console.log(`Deleting ${RELATIVE_MODULE_PATH}`);
    rmSync(RELATIVE_MODULE_PATH, { recursive: true, force: true });
  }

  const NAME = KEY.match(/^submodule\.(.*)\.path$/)[1];
  const URL = runGit(['config', '-f', '.gitmodules', '--get', `submodule.${NAME}.url`]).trim();

  let BRANCH = 'master';
  try {
    BRANCH = runGit(['config', '-f', '.gitmodules', '--get', `submodule.${NAME}.branch`]).trim();
  } catch {}

  const addResult = runGit([
    '-C', REPO_PATH,
    'submodule', 'add', '--force', '-b', BRANCH,
    '--name', NAME, URL, MODULE_PATH
  ], true);

  if (addResult.status !== 0) {
    console.warn(`Cannot add submodule ${MODULE_PATH}`);
    continue;
  }

  const repo = URL.replace('https://github.com/', '');
  const GIT_MODULES = join(RELATIVE_MODULE_PATH, '.gitmodules');

  // If access token is set, use it
  if (process.env.ACCESS_TOKEN) {
    const URL_WITH_TOKEN = `https://${process.env.ACCESS_TOKEN}@github.com/${repo}`;
    console.log(`Apply token for ${repo} at ${MODULE_PATH} branch ${BRANCH}`);
    runGit(['-C', RELATIVE_MODULE_PATH, 'remote', 'set-url', 'origin', URL_WITH_TOKEN]);
  }

  runGit(['-C', RELATIVE_MODULE_PATH, 'fetch', '--all']);
  runGit(['-C', RELATIVE_MODULE_PATH, 'pull', 'origin', BRANCH, '-X', 'theirs']);

  if (existsSync(GIT_MODULES)) {
    console.log(`${MODULE_PATH} has submodules`);
    // Recursively run self
    const result = spawnSync('node', [__filename, '-cwd', RELATIVE_MODULE_PATH], { stdio: 'inherit' });
    if (result.status !== 0) {
      console.error(`Recursive submodule failed for ${RELATIVE_MODULE_PATH}`);
      process.exit(result.status);
    }
  }
}

// Final update all
runGit(['-C', REPO_PATH, 'submodule', 'update', '--init', '--recursive']);


// ----------- Helper Functions -----------

function runGit(args, returnResult = false) {
  const result = spawnSync('git', args, { encoding: 'utf-8' });

  if (returnResult) return result;

  if (result.status !== 0) {
    throw new Error(result.stderr || `git ${args.join(' ')} failed`);
  }

  return result.stdout || '';
}
