import { spawn } from 'child_process';
import { PYTHON, PROJECT_DIR } from './config.js';

/**
 * Nuitka installation configuration
 * Specify one of: version, tarball, or git
 * @type {{version?: string, tarball?: string, git?: string, forceReinstall?: boolean}}
 */
const NUITKA_INSTALL = {
  // Option 1: Install specific version (e.g., '1.8.0')
  // version: '1.8.0',

  // Option 2: Install from tarball URL
  // tarball: 'https://github.com/Nuitka/Nuitka/archive/refs/tags/1.8.0.tar.gz',

  // Option 3: Install from git commit
  // git: 'git+https://github.com/Nuitka/Nuitka.git@commit-hash',

  // Force reinstall even if already installed
  forceReinstall: true,

  // Default: Install latest version
  version: 'latest'
};

// Build pip install command
function getPipCommand() {
  const args = ['-m', 'pip', 'install'];

  if (NUITKA_INSTALL.forceReinstall) {
    args.push('--force-reinstall');
  }

  let package_spec;
  if (NUITKA_INSTALL.version && NUITKA_INSTALL.version !== 'latest') {
    package_spec = `nuitka==${NUITKA_INSTALL.version}`;
  } else if (NUITKA_INSTALL.tarball) {
    package_spec = NUITKA_INSTALL.tarball;
  } else if (NUITKA_INSTALL.git) {
    package_spec = NUITKA_INSTALL.git;
  } else {
    package_spec = 'nuitka'; // latest version
  }

  return { args, package_spec };
}

const { args, package_spec } = getPipCommand();

console.log(`PROJECT_DIR=${PROJECT_DIR}`);
console.log(`PYTHON=${PYTHON}`);
console.log(`Installing nuitka: ${package_spec}\n`);

// Execute pip install
const child = spawn(PYTHON, [...args, package_spec], {
  stdio: 'inherit',
  cwd: PROJECT_DIR
});

child.on('close', (code) => {
  if (code === 0) {
    console.log('\nNuitka installation completed successfully!');
    process.exit(0);
  } else {
    console.error(`Installation failed with exit code ${code}`);
    process.exit(code);
  }
});

child.on('error', (err) => {
  console.error('Failed to start installation process:', err);
  process.exit(1);
});
