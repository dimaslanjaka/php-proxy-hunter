const fs = require('fs').promises;
const path = require('path');
const crypto = require('crypto');
const glob = require('glob');

/**
 * Generate file hashes for a project directory.
 * @param {Object} options
 * @param {string} options.projectDir - The root directory of the project.
 * @param {string[]} [options.extensions] - File extensions to include (no dot).
 * @param {string[]} [options.excludeDirs] - Directories to exclude (relative to projectDir).
 * @param {string[]} [options.extraFiles] - Extra files to always include (absolute paths).
 * @returns {Promise<string[]>} Array of strings in the format 'relative/path/to/file hash'.
 */
async function createFileHashes({
  projectDir,
  extensions = ['py', 'js', 'php', 'cjs', 'mjs'],
  excludeDirs = [],
  extraFiles = []
}) {
  // Convert excludeDirs into glob ignore patterns
  const ignorePatterns = excludeDirs.map((dir) => `**/${dir}/**`);
  let hashArray = [];
  const processedFiles = new Set();

  async function hashAndPush(file) {
    if (processedFiles.has(file)) return;
    processedFiles.add(file);
    try {
      const stats = await fs.stat(file);
      const pseudoHash = `${stats.size}-${stats.mtimeMs}`;
      const hash = crypto.createHash('sha256').update(pseudoHash).digest('hex');
      let relativePath = path.relative(projectDir, file);
      relativePath = relativePath.split(path.sep).join('/');
      hashArray.push(`${relativePath} ${hash.slice(0, 8)}`);
    } catch {
      // Ignore errors for missing files
    }
  }

  let allFiles = new Set(extraFiles);
  extensions.forEach((ext) => {
    glob
      .sync(`**/*.${ext}`, {
        cwd: projectDir,
        ignore: ignorePatterns,
        absolute: true
      })
      .forEach((f) => allFiles.add(f));
  });

  await Promise.all(Array.from(allFiles).map(hashAndPush));
  hashArray.sort((a, b) => a.localeCompare(b));
  return hashArray;
}

/**
 * Generates a directory/file tree string from a hash array of file paths and hashes.
 * @param {string[]} hashArray - Array of strings in the format 'relative/path/to/file hash'.
 * @returns {string} The directory/file tree as a string, with file hashes.
 */
function getFileTreeString(hashArray) {
  const tree = {};
  const hashMap = {};
  for (const entry of hashArray) {
    const [filePath, hash] = entry.split(' ');
    hashMap[filePath] = hash;
    const parts = filePath.split('/');
    let current = tree;
    for (let i = 0; i < parts.length; i++) {
      const part = parts[i];
      if (i === parts.length - 1) {
        current[part] = null;
      } else {
        current[part] = current[part] || {};
        current = current[part];
      }
    }
  }
  function printNode(node, prefix = '', parentPath = '') {
    const keys = Object.keys(node).sort();
    let lines = [];
    keys.forEach((key, idx) => {
      const isLast = idx === keys.length - 1;
      const branch = isLast ? '└── ' : '├── ';
      const currentPath = parentPath ? parentPath + '/' + key : key;
      if (node[key] === null) {
        lines.push(prefix + branch + key + ' [' + (hashMap[currentPath] || '') + ']');
      } else {
        lines.push(prefix + branch + key + '/');
        lines = lines.concat(printNode(node[key], prefix + (isLast ? '    ' : '│   '), currentPath));
      }
    });
    return lines;
  }
  return printNode(tree, '', '').join('\n');
}

module.exports = { createFileHashes, getFileTreeString };
