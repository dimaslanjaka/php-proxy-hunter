import path from 'upath';
import { fileURLToPath } from 'url';
import { dirname } from 'path';
import { spawnAsync } from 'cross-spawn';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const CWD = path.resolve(__dirname, '..');
const files = [
  path.join(__dirname, 'composer-installer.js'),
  path.join(__dirname, 'sqlite-installer.js'),
  path.join(__dirname, 'php-cs-fixer-installer.js'),
  path.join(__dirname, 'geoip-installer.js'),
  path.join(__dirname, '/../src/utils/process/executables-finder.cjs')
];

async function main() {
  for (const file of files) {
    console.log(`\nExecuting post-install script: ${file}\n`);
    await spawnAsync('node', [file], { cwd: CWD, stdio: 'inherit' });
  }
}

main().catch(console.error);
