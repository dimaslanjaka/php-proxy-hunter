import * as fs from 'fs';
import * as path from 'path';
import { writefile } from 'sbg-utility';
import { projectDir } from './init.js';
import { nodeConsole } from './logger.js';

const directoryTreeFile = path.join(projectDir, 'tmp', 'directory-tree.txt');
// Reset directory tree file at startup
writefile(directoryTreeFile, '');

/**
 * Recursively print directory structure in tree format
 * @param dirPath - The directory path to print
 * @param prefix The prefix string for tree formatting
 * @param isRoot Whether this is the root call (for logging)
 */
export function printDirectory(dirPath: string, prefix = '', isRoot = true) {
  const items = fs.readdirSync(dirPath, { withFileTypes: true });

  items.forEach((item, index) => {
    const isLast = index === items.length - 1;
    const pointer = isLast ? '└── ' : '├── ';
    fs.appendFileSync(directoryTreeFile, prefix + pointer + item.name + '\n');

    if (item.isDirectory()) {
      const newPrefix = prefix + (isLast ? '    ' : '│   ');
      printDirectory(path.join(dirPath, item.name), newPrefix, false);
    }
  });

  // Only log once when the root call is complete
  if (isRoot) {
    nodeConsole.log(`Directory structure written to ${directoryTreeFile}`);
  }
}
