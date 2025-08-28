import path from 'upath';
import { fileURLToPath } from 'url';
import { dirname } from 'path';
import { spawnAsync } from 'cross-spawn';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const CWD = path.resolve(__dirname, '..');
const files = [path.join(__dirname, 'composer-installer.js')];

async function main() {
  for (const file of files) {
    await spawnAsync('node', [file], { cwd: CWD, stdio: 'inherit' });
  }
}

main().catch(console.error);
