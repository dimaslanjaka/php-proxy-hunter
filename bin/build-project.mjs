import * as cp from 'cross-spawn';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'fs-extra';
import minimist from 'minimist';
import { getChecksum } from 'sbg-utility/dist/utils/hash';
import * as glob from 'glob';

// Parse CLI args
const argv = minimist(process.argv.slice(2));
const force = argv.force === true;

// Setup paths
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const cwd = path.join(__dirname, '../');
const tmpDir = path.join(cwd, 'tmp/locks');
fs.ensureDirSync(tmpDir);

// Checksum files
const checksumInstallFile = path.join(tmpDir, 'build-project-checksum-install.txt');
const checksumBuildFile = path.join(tmpDir, 'build-project-checksum-build.txt');

// Read previous checksums unless forcing
const lastInstallChecksum =
  !force && fs.existsSync(checksumInstallFile) ? fs.readFileSync(checksumInstallFile, 'utf-8').trim() : null;

const lastBuildChecksum =
  !force && fs.existsSync(checksumBuildFile) ? fs.readFileSync(checksumBuildFile, 'utf-8').trim() : null;

// Collect files
const packageJsonFiles = glob
  .sync('**/package.json', {
    cwd,
    nodir: true,
    dot: true,
    ignore: [
      '**/node_modules/**',
      '**/*venv/**',
      '**/.yarn/**',
      '**/vendor/**',
      '**/tmp/**',
      '**/dist/**',
      '**/.git/**',
      '**/build/**',
      '**/.cache/**'
    ]
  })
  .map((f) => path.join(cwd, f))
  .sort();

const currentInstallChecksum = getChecksum(...packageJsonFiles);

const srcFiles = glob
  .sync('src/**/*.{js,jsx,ts,tsx,cjs,mjs}', { cwd, nodir: true, dot: true })
  .map((f) => path.join(cwd, f))
  .sort();

const currentBuildChecksum = getChecksum(...srcFiles);

// Perform yarn install
if (force || lastInstallChecksum !== currentInstallChecksum) {
  console.log(force ? '[force] Running yarn install...' : 'Detected changes in package.json. Running yarn install...');

  cp.spawnSync('yarn', ['install'], {
    cwd,
    shell: true,
    stdio: 'inherit'
  });

  // Write updated checksum
  fs.writeFileSync(checksumInstallFile, currentInstallChecksum, 'utf-8');
} else {
  console.log('No changes in package.json files. Skipping yarn install.');
}

// Perform yarn build
if (force || lastBuildChecksum !== currentBuildChecksum) {
  console.log(force ? '[force] Running yarn build...' : 'Detected changes in src files. Running yarn build...');

  cp.spawnSync('yarn', ['build'], {
    cwd,
    shell: true,
    stdio: 'inherit'
  });

  // Write updated checksum
  fs.writeFileSync(checksumBuildFile, currentBuildChecksum, 'utf-8');
} else {
  console.log('No changes in src files. Skipping build.');
}
