import * as cp from 'cross-spawn';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'fs-extra';
import { getChecksum } from 'sbg-utility/dist/utils/hash';
import * as glob from 'glob';

// Setup paths
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const cwd = path.join(__dirname, '../');
const tmpDir = path.join(cwd, 'tmp/build');
fs.ensureDirSync(tmpDir);

// Checksum files
const checksumInstallFile = path.join(tmpDir, 'build-project-checksum-install.txt');
const checksumBuildFile = path.join(tmpDir, 'build-project-checksum-build.txt');

// Calculate checksums

const lastInstallChecksum = fs.existsSync(checksumInstallFile)
  ? fs.readFileSync(checksumInstallFile, 'utf-8').trim()
  : null;
const lastBuildChecksum = fs.existsSync(checksumBuildFile) ? fs.readFileSync(checksumBuildFile, 'utf-8').trim() : null;

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

// Run yarn install if package.json changed
if (lastInstallChecksum !== currentInstallChecksum) {
  cp.spawnSync('yarn', ['install'], {
    cwd,
    shell: true,
    stdio: 'inherit'
  });
  fs.writeFileSync(checksumInstallFile, currentInstallChecksum, 'utf-8');
}

// Run yarn build if src changed
if (lastBuildChecksum !== currentBuildChecksum) {
  cp.spawnSync('yarn', ['build'], {
    cwd,
    shell: true,
    stdio: 'inherit'
  });
  fs.writeFileSync(checksumBuildFile, currentBuildChecksum, 'utf-8');
}
