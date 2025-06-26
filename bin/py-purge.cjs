const { execSync } = require('child_process');
const fs = require('fs');
const upath = require('upath');
const glob = require('glob');

// Get the script directory (normalized to Unix-style)
const SCRIPT_DIR = upath.dirname(__filename);
const CWD = upath.resolve(SCRIPT_DIR, '..');

console.log(`Working directory: ${CWD}`);

// Path to virtual environment
const venvPath = upath.join(CWD, 'venv');

// Create virtual environment if it doesn't exist
if (!fs.existsSync(venvPath)) {
  console.log('Creating virtual environment...');
  try {
    execSync('pip install virtualenv', { stdio: 'inherit', shell: true });
  } catch (err) {
    console.error('Failed to install virtualenv:', err.message);
    process.exit(1);
  }

  execSync(`python3 -m venv "${venvPath}"`, { stdio: 'inherit', shell: true });
} else {
  console.log('Virtual environment already exists.');
}

// Helper to run a command inside the virtual environment
function runInVenv(command) {
  const activate =
    process.platform === 'win32'
      ? upath.join(venvPath, 'Scripts', 'activate.bat')
      : upath.join(venvPath, 'bin', 'activate');

  const fullCommand =
    process.platform === 'win32' ? `"${activate}" && ${command}` : `source "${activate}" && ${command}`;

  execSync(fullCommand, { stdio: 'inherit', shell: true });
}

// Clear Python __pycache__ using glob.sync
console.log('Clearing Python cache...');

const matches = glob.sync(`${CWD}/**/__pycache__`, {
  nodir: false,
  ignore: ['**/venv/**', '**/tmp/**', '**/.cache/**', '**/build/**', '**/node_modules/**', '**/vendor/**', '**/dist/**']
});

console.log(`Found ${matches.length} __pycache__ directories.`);

matches.forEach((dir) => {
  console.log(`Deleting: ${dir}`);
  try {
    fs.rmSync(dir, { recursive: true, force: true });
  } catch (err) {
    console.warn(`Failed to delete ${dir}: ${err.message}`);
  }
});

console.log('Clearing pip cache...');
runInVenv('python -m pip cache purge');
