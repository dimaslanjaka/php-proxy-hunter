#!/usr/bin/env node

'use strict';

const path = require('path');
const fs = require('fs');
const {
  createVenvUnix,
  ensurePython3ShimUnix,
  resolvePythonBinUnix,
  fixLinuxNginxPermissions,
  PLATFORM,
  runPython,
  createVenvWindows,
  ensurePython3ShimWindows,
  resolvePythonBinWindows
} = require('./python-wrapper.cjs');

const SCRIPT_DIR = path.dirname(fs.realpathSync(__filename));
const PYZ_PATH = path.join(SCRIPT_DIR, 'proxy-checker-opencode.pyz');

const args = [PYZ_PATH, ...process.argv.slice(2)];

if (PLATFORM === 'linux' || PLATFORM === 'macos') {
  createVenvUnix();
  ensurePython3ShimUnix();

  const PYTHON_BIN = resolvePythonBinUnix();

  fixLinuxNginxPermissions();

  runPython(PYTHON_BIN, args);
}

if (PLATFORM === 'windows') {
  createVenvWindows();
  ensurePython3ShimWindows();

  const PYTHON_BIN = resolvePythonBinWindows();

  runPython(PYTHON_BIN, args);
}
