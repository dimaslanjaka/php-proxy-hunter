const fs = require('fs').promises;
const path = require('path');
const crypto = require('crypto');
const { execSync } = require('child_process');
const glob = require('glob');

// Determine the current script directory and project directory
const scriptDir = path.dirname(__filename);
const projectDir = path.dirname(scriptDir);
const envPath = path.join(projectDir, '.env');

// Load the .env file if it exists
(async () => {
  if (await fs.stat(envPath).catch(() => false)) {
    const envContent = await fs.readFile(envPath, 'utf-8');
    envContent.split('\n').forEach((line) => {
      if (line && !line.startsWith('#')) {
        const [key, value] = line.split('=');
        process.env[key.trim()] = value.trim();
      }
    });
    console.log(`${envPath} file loaded`);
  }

  // Define the output file
  const relativeOutputFile = '.husky/hash.txt';
  const outputFile = path.join(projectDir, relativeOutputFile);

  // Create or clear the hash file
  await fs.writeFile(outputFile, '');

  // List of file extensions to include
  const extensions = ['py', 'js', 'php', 'cjs', 'mjs'];

  // Directories to exclude
  const excludeDirs = [
    'dashboard',
    'bin',
    'node_modules',
    'vendor',
    'venv',
    '.yarn',
    '__pycache__',
    'docs',
    'xl',
    'django_backend',
    'userscripts',
    'webalizer',
    '.cache',
    'tests',
    'example',
    '.husky',
    'packages',
    'dist',
    'tmp',
    'config',
    'data/script',
    'logs',
    'data/run',
    'data/engine',
    'data/fingerprints',
    'site-packages',
    '.vscode'
  ];

  // Convert excludeDirs into glob ignore patterns
  const ignorePatterns = excludeDirs.map((dir) => `**/${dir}/**`);

  // Initialize an array to hold the formatted outputs
  let hashArray = [];

  // Helper function to hash and add file if not already processed
  const processedFiles = new Set();
  /**
   * Hashes a file and pushes its relative path and hash to the hashArray.
   * @param {string} file - The absolute path to the file.
   * @returns {Promise<void>} Resolves when the file has been processed and added to hashArray.
   */
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
    } catch (err) {
      console.error(`Error processing file: ${file}`, err);
    }
  }

  // Collect all files to process (extensions + special files)
  let allFiles = new Set();
  allFiles.add(path.join(projectDir, 'package.json'));
  allFiles.add(path.join(projectDir, 'composer.json'));
  allFiles.add(path.join(projectDir, 'requirements.txt'));
  allFiles.add(path.join(projectDir, 'requirements-dev.txt'));
  extensions.forEach((ext) => {
    glob
      .sync(`**/*.${ext}`, {
        cwd: projectDir,
        ignore: ignorePatterns,
        absolute: true
      })
      .forEach((f) => allFiles.add(f));
  });

  // Hash all unique files
  await Promise.all(Array.from(allFiles).map(hashAndPush));

  // Sort the hashArray by file paths
  hashArray.sort((a, b) => a.localeCompare(b));

  // Generates a directory/file tree string from a hash array of file paths and hashes.
  /**
   * Generates a directory/file tree string from a hash array of file paths and hashes.
   *
   * @param {string[]} hashArray - Array of strings in the format 'relative/path/to/file hash'.
   * @returns {string} The directory/file tree as a string, with file hashes.
   */
  function getFileTreeString(hashArray) {
    const tree = {};
    // Map file paths to hashes for quick lookup
    const hashMap = {};
    for (const entry of hashArray) {
      const [filePath, hash] = entry.split(' ');
      hashMap[filePath] = hash;
      const parts = filePath.split('/');
      let current = tree;
      for (let i = 0; i < parts.length; i++) {
        const part = parts[i];
        if (i === parts.length - 1) {
          current[part] = null; // file
        } else {
          current[part] = current[part] || {};
          current = current[part];
        }
      }
    }
    /**
     * Recursively builds the tree string for a given node.
     *
     * @param {Object} node - The current node in the tree.
     * @param {string} prefix - The prefix for the current tree level.
     * @param {string} parentPath - The path to the current node.
     * @returns {string[]} Array of lines representing the tree structure.
     */
    function printNode(node, prefix = '', parentPath = '') {
      const keys = Object.keys(node).sort();
      let lines = [];
      keys.forEach((key, idx) => {
        const isLast = idx === keys.length - 1;
        const branch = isLast ? '└── ' : '├── ';
        const currentPath = parentPath ? parentPath + '/' + key : key;
        if (node[key] === null) {
          // file: show hash
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

  // Write directory/file tree to the output file (hashes are included in the tree)
  const fileTreeString = getFileTreeString(hashArray);
  await fs.writeFile(outputFile, fileTreeString + '\n', 'utf-8');

  // Add the hash file to the commit
  execSync(`git add ${relativeOutputFile}`);

  console.log(`Hashes saved to ${relativeOutputFile}`);
})();
