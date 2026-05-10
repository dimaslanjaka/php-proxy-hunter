const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs').promises;
const { createFileHashes, getFileTreeString } = require('sbg-utility');
const dotenv = require('dotenv');

// Determine the current script directory and project directory
const scriptDir = path.dirname(__filename);
const projectDir = path.dirname(scriptDir);
const envPath = path.join(projectDir, '.env');

// Load the .env file using dotenv
dotenv.config({ path: envPath, quiet: true, override: true });

const createFileHashesMain = async () => {
  // Define the output file
  const relativeOutputFile = '.husky/hash.txt';
  const outputFile = path.join(projectDir, relativeOutputFile);

  // List of file extensions to include
  const extensions = ['py', 'js', 'php', 'cjs', 'mjs', 'ts', 'tsx'];
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
  // Extra files to always include
  const extraFiles = [
    path.join(projectDir, 'package.json'),
    path.join(projectDir, 'composer.json'),
    path.join(projectDir, 'requirements.txt'),
    path.join(projectDir, 'requirements-dev.txt')
  ];

  // Generate hashes
  const hashMap = await createFileHashes({
    projectDir: path.join(projectDir, 'src'),
    extensions,
    excludeDirs,
    extraFiles
  });

  // Write directory/file tree to the output file (hashes are included in the tree)
  const fileTreeString = getFileTreeString(hashMap);
  await fs.writeFile(outputFile, fileTreeString + '\n', 'utf-8');

  // Add the hash file to the commit
  execSync(`git add ${relativeOutputFile}`);

  console.log(`Hashes saved to ${relativeOutputFile}`);
};

module.exports = {
  createFileHashes,
  getFileTreeString,
  createFileHashesMain
};

if (require.main === module) {
  // This script is being run directly
  createFileHashesMain().catch((err) => {
    console.error('Error occurred while creating file hashes:', err);
    process.exit(1);
  });
}
