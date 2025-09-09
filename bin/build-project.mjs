import * as cp from 'cross-spawn';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'fs-extra';
import { getChecksum } from 'sbg-utility/dist/utils/hash';
import * as glob from 'glob';

// Define the current working directory (base path)
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const cwd = path.join(__dirname, '../');
const checksumFile = path.join(cwd, 'tmp/build/build-project-checksum.txt');
fs.ensureDirSync(path.dirname(checksumFile));
const lastChecksum = fs.existsSync(checksumFile) ? fs.readFileSync(checksumFile, 'utf-8').trim() : null;
const currentChecksum = getChecksum(
  path.join(cwd, 'package.json'),
  ...glob
    .sync('src/**/*.{js,jsx,ts,tsx}', { cwd, nodir: true, dot: true })
    .map((f) => path.join(cwd, f))
    .sort()
);

if (lastChecksum !== currentChecksum) {
  cp.spawnSync('yarn', ['install'], { stdio: 'inherit', cwd });
  cp.spawnSync('npm', ['run', 'build'], {
    stdio: 'inherit',
    cwd
  });
  fs.writeFileSync(checksumFile, currentChecksum, 'utf-8');
}
