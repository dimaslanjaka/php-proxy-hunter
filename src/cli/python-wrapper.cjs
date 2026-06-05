#!/usr/bin/env node

'use strict';

const fs = require('fs');
const path = require('path');
const { spawn, spawnSync } = require('cross-spawn');

const PLATFORM = (() => {
  switch (process.platform) {
    case 'linux':
      return 'linux';
    case 'darwin':
      return 'macos';
    case 'win32':
      return 'windows';
    default:
      console.error(`Unsupported OS: ${process.platform}`);
      process.exit(1);
  }
})();

const SCRIPT_DIR = path.dirname(fs.realpathSync(__filename));
const CWD = path.dirname(SCRIPT_DIR);

if (!fs.existsSync(CWD) || !fs.statSync(CWD).isDirectory()) {
  console.error(`Directory ${CWD} does not exist.`);
  process.exit(1);
}

// Prefer venv over .venv
const VENV_PATH =
  fs.existsSync(path.join(CWD, 'venv')) || !fs.existsSync(path.join(CWD, '.venv'))
    ? path.join(CWD, 'venv')
    : path.join(CWD, '.venv');

const USER = 'www-data';

function exists(filePath) {
  return fs.existsSync(filePath);
}

function isFile(filePath) {
  return exists(filePath) && fs.statSync(filePath).isFile();
}

function isDir(dirPath) {
  return exists(dirPath) && fs.statSync(dirPath).isDirectory();
}

function isExecutable(filePath) {
  if (!isFile(filePath)) return false;

  if (PLATFORM === 'windows') {
    return true;
  }

  try {
    fs.accessSync(filePath, fs.constants.X_OK);
    return true;
  } catch {
    return false;
  }
}

function commandExists(command) {
  const checker = PLATFORM === 'windows' ? 'where' : 'command';
  const args = PLATFORM === 'windows' ? [command] : ['-v', command];

  const result = spawnSync(checker, args, {
    stdio: 'ignore',
    shell: PLATFORM !== 'windows'
  });

  return result.status === 0;
}

function run(command, args, options = {}) {
  const result = spawnSync(command, args, {
    stdio: 'inherit',
    shell: false,
    ...options
  });

  if (result.error) {
    console.error(result.error.message);
    process.exit(1);
  }

  if (result.status !== 0) {
    process.exit(result.status ?? 1);
  }
}

function ensurePython3ShimUnix() {
  const python = path.join(VENV_PATH, 'bin', 'python');
  const python3 = path.join(VENV_PATH, 'bin', 'python3');

  if (isFile(python) && !exists(python3)) {
    console.log('Creating python3 symlink...');
    fs.symlinkSync('python', python3);
  }
}

function ensurePython3ShimWindows() {
  const python = path.join(VENV_PATH, 'Scripts', 'python.exe');
  const python3 = path.join(VENV_PATH, 'Scripts', 'python3.exe');

  if (isFile(python) && !exists(python3)) {
    console.log('Creating python3.exe shim...');
    fs.copyFileSync(python, python3);
  }
}

function resolvePythonBinUnix() {
  const python3 = path.join(VENV_PATH, 'bin', 'python3');
  const python = path.join(VENV_PATH, 'bin', 'python');

  return isExecutable(python3) ? python3 : python;
}

function resolvePythonBinWindows() {
  const python3 = path.join(VENV_PATH, 'Scripts', 'python3.exe');
  const python = path.join(VENV_PATH, 'Scripts', 'python.exe');

  return isExecutable(python3) ? python3 : python;
}

function chmodBinFilesUnix() {
  const binDir = path.join(VENV_PATH, 'bin');

  if (!isDir(binDir)) return;

  for (const file of fs.readdirSync(binDir)) {
    const filePath = path.join(binDir, file);

    try {
      fs.chmodSync(filePath, 0o755);
    } catch {
      // Same behavior as: chmod ... || true
    }
  }
}

function fixLinuxNginxPermissions() {
  if (PLATFORM !== 'linux') return;
  if (!commandExists('nginx')) return;
  if (!commandExists('sudo')) return;

  spawnSync('sudo', ['-n', 'chown', '-R', `${USER}:${USER}`, VENV_PATH], {
    stdio: 'ignore'
  });

  // Node-native replacement for:
  // sudo -n chmod 755 "$VENV_PATH/bin"/*
  chmodBinFilesUnix();
}

function createVenvUnix() {
  const activate = path.join(VENV_PATH, 'bin', 'activate');

  if (!isDir(VENV_PATH) || !isFile(activate)) {
    console.log('Creating virtual environment...');
    run('python3', ['-m', 'venv', VENV_PATH]);
    console.log('Virtual environment created.');
  }
}

function createVenvWindows() {
  const activate = path.join(VENV_PATH, 'Scripts', 'activate');

  if (!isDir(VENV_PATH) || !isFile(activate)) {
    console.log('Creating virtual environment...');
    run('python3.exe', ['-m', 'venv', VENV_PATH]);
    console.log('Virtual environment created.');
  }
}

function runPython(pythonBin, args) {
  const child = spawn(pythonBin, args, {
    stdio: 'inherit',
    shell: false
  });

  child.on('error', (error) => {
    console.error(error.message);
    process.exit(1);
  });

  child.on('exit', (code, signal) => {
    if (signal) {
      console.error(`Python process terminated by signal: ${signal}`);
      process.exit(1);
    }

    process.exit(code ?? 0);
  });
}

module.exports = {
  ensurePython3ShimUnix,
  ensurePython3ShimWindows,
  resolvePythonBinUnix,
  resolvePythonBinWindows,
  fixLinuxNginxPermissions,
  createVenvUnix,
  createVenvWindows,
  runPython,
  PLATFORM
};
