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

const args = process.argv.slice(2);

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
