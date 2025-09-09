import { spawnSync } from 'child_process';
import { fileURLToPath } from 'url';
import path from 'path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

async function isAlreadyInstalled() {
  // List of packages to check
  const packages = ['gulp', 'glob', 'sbg-utility', 'upath', 'fs-extra'];

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

  return isInstalled;
}

/**
 * Installs dependencies if not already installed or if the checksum of package.json has changed.
 * Uses fs-extra, sbg-utility, and upath for file and checksum operations.
 *
 * - If dependencies are not installed, compares the checksum of package.json with a stored value.
 * - If the checksum differs, runs 'yarn install' and updates the stored checksum.
 *
 * @returns {Promise<void>} Resolves when dependency installation and checksum update are complete.
 */
async function installDependencies() {
  const isInstalled = await isAlreadyInstalled();

  if (isInstalled) {
    const fs = await import('fs-extra').then((mod) => mod.default ?? mod);
    const sbgUtility = await import('sbg-utility');
    const path = await import('upath').then((mod) => mod.default ?? mod);

    // Reinstall dependencies if package.json checksum has changed
    const checksum = sbgUtility.getChecksum(path.join(__dirname, 'package.json'));
    const fileChecksum = path.join(__dirname, 'tmp/build/setup-checksum.txt');
    const previousChecksum = fs.existsSync(fileChecksum) ? fs.readFileSync(fileChecksum, 'utf-8') : '';
    if (checksum !== previousChecksum) {
      const result = spawnSync('yarn', ['install'], { stdio: 'inherit', shell: true });
      if (result.error) {
        console.error('Failed to run yarn install:', result.error);
        process.exit(result.status || 1);
      }
      fs.ensureDirSync(path.dirname(fileChecksum));
      fs.writeFileSync(fileChecksum, checksum);
      console.log('Checksum updated:', checksum);
    }
  } else {
    // Dependencies not installed
    const result = spawnSync('yarn', ['install'], { stdio: 'inherit', shell: true });
    if (result.error) {
      console.error('Failed to run yarn install:', result.error);
      process.exit(result.status || 1);
    }
  }
}

/**
 * Runs the setup tasks: loads env, builds Tailwind, copies index.html, updates browserslist-db.
 */
async function runSetup() {
  await import('./.env.mjs');
  const { buildTailwind } = await import('./tailwind.build.js');
  const { copyIndexHtml } = await import('./vite-plugin.js');
  const { spawnSync } = await import('child_process');

  buildTailwind();
  copyIndexHtml();
  spawnSync('npx', ['--yes', 'update-browserslist-db@latest'], { stdio: 'inherit', shell: true });
}

/**
 * Checks if a package is installed (resolvable) in ESM context.
 * @param {string} packageName - The name of the package to check.
 * @returns {Promise<boolean>} - Resolves to true if installed, false otherwise.
 */
async function isPackageInstalled(packageName) {
  try {
    await import(packageName);
    return true;
  } catch {
    return false;
  }
}

// run the setup tasks
installDependencies().then(runSetup);
