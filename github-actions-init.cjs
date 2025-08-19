const { spawnSync } = require('child_process');

// List of packages to check
const packages = ['gulp', 'glob', 'sbg-utility', 'upath', 'fs-extra'];

/**
 * Check if a Node.js package is installed (resolvable from current context).
 * @param {string} packageName - The name of the package to check.
 * @returns {boolean} True if the package is installed, false otherwise.
 */
const isPackageInstalled = (packageName) => {
  try {
    require.resolve(packageName);
    return true;
  } catch (_err) {
    return false;
  }
};

// Check if any package is not installed
const missingPackages = packages.filter((packageName) => !isPackageInstalled(packageName));
let isInstalled = false;

if (missingPackages.length > 0) {
  console.log('Some packages are missing:', missingPackages.join(', '));
  console.log('Running yarn install...');
  const result = spawnSync('yarn', ['install'], { stdio: 'inherit', shell: true });
  if (result.error) {
    console.error('Failed to run yarn install:', result.error);
    process.exit(result.status || 1);
  }
  isInstalled = true;
} else {
  console.log('All packages are installed.');
}

// imports
const sbgUtility = require('sbg-utility');
const path = require('upath');
const fs = require('fs-extra');

// Compare checksum when not yet installed
if (!isInstalled) {
  const checksum = sbgUtility.getChecksum(path.join(__dirname, 'package.json'));
  const fileChecksum = path.join(__dirname, 'tmp/checksum.txt');
  const previousChecksum = fs.existsSync(fileChecksum) ? fs.readFileSync(fileChecksum, 'utf-8') : '';
  if (checksum !== previousChecksum) {
    const result = spawnSync('yarn', ['install'], { stdio: 'inherit', shell: true });
    if (result.error) {
      console.error('Failed to run yarn install:', result.error);
      process.exit(result.status || 1);
    }
    fs.writeFileSync(fileChecksum, checksum);
    console.log('Checksum updated:', checksum);
  }
}
