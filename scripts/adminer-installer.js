import { spawnAsync } from 'cross-spawn';
import fs from 'fs-extra';
import path from 'upath';

// Destination folder
const DEST = path.join(process.cwd(), 'adminer');
if (!fs.existsSync(DEST)) fs.mkdirSync(DEST, { recursive: true });

async function main() {
  if (!fs.existsSync(path.join(DEST, '.git'))) {
    if (fs.existsSync(DEST)) {
      fs.rmSync(DEST, { recursive: true, force: true });
    }
    await spawnAsync('git', ['clone', 'https://github.com/vrana/adminer.git', DEST], { stdio: 'inherit' });
  }
  // Update submodule
  await spawnAsync('git', ['submodule', 'update', '--init', '--recursive'], { stdio: 'inherit', cwd: DEST });
  // Install or Update composer dependencies
  const composerLockPath = path.join(DEST, 'composer.lock');
  if (!fs.existsSync(composerLockPath)) {
    await spawnAsync('composer', ['install'], { cwd: DEST, stdio: 'inherit' });
  } else {
    // If composer.lock exists, run composer update to ensure all dependencies are up to date
    await spawnAsync('composer', ['update'], { cwd: DEST, stdio: 'inherit' });
  }

  const adminerIndex = path.join(DEST, 'adminer/index.php');
  // restore adminer/index.php
  await spawnAsync('git', ['restore', adminerIndex], { cwd: DEST, stdio: 'inherit' });
  // Modify content for adminer/index.php
  const funcPhpPath = path.join(process.cwd(), 'func.php');
  // Calculate relative path from adminer/index.php to func.php
  const relativeFuncPhp = path.relative(path.dirname(adminerIndex), funcPhpPath).replace(/\\/g, '/');
  const adminerContent = fs.readFileSync(adminerIndex, 'utf-8');
  const adminerContentMod = adminerContent.replace(
    /namespace Adminer;/,
    `namespace Adminer;

require_once __DIR__ . '/${relativeFuncPhp}';
// only allow administrator
// edit administrator email on /data/login.php
if (!isset($_SESSION['admin'])) {
  exit('disallow access');
}

      `
  );
  fs.writeFileSync(adminerIndex, adminerContentMod, 'utf-8');

  // Do the same for editor/index.php if it exists
  const editorIndex = path.join(DEST, 'editor/index.php');
  if (fs.existsSync(editorIndex)) {
    // restore editor/index.php
    await spawnAsync('git', ['restore', editorIndex], { cwd: DEST, stdio: 'inherit' });
    const relativeFuncPhpEditor = path.relative(path.dirname(editorIndex), funcPhpPath).replace(/\\/g, '/');
    const editorContent = fs.readFileSync(editorIndex, 'utf-8');
    const editorContentMod = editorContent.replace(
      /namespace Adminer;/,
      `namespace Adminer;

require_once __DIR__ . '/${relativeFuncPhpEditor}';
// only allow administrator
// edit administrator email on /data/login.php
if (!isset($_SESSION['admin'])) {
  exit('disallow access');
}

      `
    );
    fs.writeFileSync(editorIndex, editorContentMod, 'utf-8');
  }
}

main().catch(console.error);
