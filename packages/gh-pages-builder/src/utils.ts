import * as fs from 'fs';
import * as path from 'path';
import { writefile } from 'sbg-utility';
import { projectDir } from './init.js';

const directoryTreeFile = path.join(projectDir, 'tmp', 'directory-tree.txt');
// Reset directory tree file at startup
writefile(directoryTreeFile, '');

/**
 * Recursively print directory structure in tree format
 * @param dirPath - The directory path to print
 * @param prefix The prefix string for tree formatting
 */
export function printDirectory(dirPath: string, prefix = '') {
  const items = fs.readdirSync(dirPath, { withFileTypes: true });

  items.forEach((item, index) => {
    const isLast = index === items.length - 1;
    const pointer = isLast ? '└── ' : '├── ';
    fs.appendFileSync(directoryTreeFile, prefix + pointer + item.name);

    if (item.isDirectory()) {
      const newPrefix = prefix + (isLast ? '    ' : '│   ');
      printDirectory(path.join(dirPath, item.name), newPrefix);
    }
  });
}
