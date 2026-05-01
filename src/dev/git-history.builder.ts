import path from 'upath';
import fs from 'fs-extra';
import { gitHistoryToJson } from './git-history-to-json';

function main() {
  // Get last [n] commits by default to avoid buffer issues
  const data = gitHistoryToJson({ last: 1000 });
  const outDir = path.join(process.cwd(), 'public', 'data');
  const outFiles = [
    path.join(outDir, 'git-history.json'),
    path.join(process.cwd(), 'dist/react/data/git-history.json')
  ];

  const tmpDir = path.join(process.cwd(), 'tmp', 'build');

  for (const outFile of outFiles) {
    const relativePath = path.relative(process.cwd(), outFile);
    const tmpFile = path.join(tmpDir, relativePath);

    // Ensure tmp directory exists for this file
    fs.ensureDirSync(path.dirname(tmpFile));

    const content = JSON.stringify(data, null, 2);

    // Write to tmp first
    fs.writeFileSync(tmpFile, content, 'utf-8');

    // If destination exists and contents are identical, skip replacing it
    if (fs.existsSync(outFile)) {
      try {
        const existing = fs.readFileSync(outFile, 'utf-8');
        if (existing === content) {
          fs.removeSync(tmpFile);
          console.log(`No changes for ${relativePath}, skipping write.`);
          continue;
        }
      } catch (err) {
        // If reading fails, fall through to overwrite
        console.warn(`Could not read existing file ${relativePath}:`, err);
      }
    } else {
      // Ensure destination directory exists when creating new file
      fs.ensureDirSync(path.dirname(outFile));
    }

    // Atomically move tmp file to destination (overwrite)
    try {
      fs.moveSync(tmpFile, outFile, { overwrite: true });
      console.log(`Git history written to ${relativePath}`);
    } catch (err) {
      console.error(`Failed to write ${relativePath}:`, err);
    }
  }
}

main();
