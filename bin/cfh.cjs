const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs').promises;
const { createFileHashes, getFileTreeString } = require('./file-hasher.cjs');
const dotenv = require('dotenv');
const crypto = require('crypto');

// Determine the current script directory and project directory
const scriptDir = path.dirname(__filename);
const projectDir = path.dirname(scriptDir);
const envPath = path.join(projectDir, '.env');

// Load the .env file using dotenv
dotenv.config({ path: envPath });
if (require('fs').existsSync(envPath)) {
  console.log(`${envPath} file loaded`);
}

const createFileHashesMain = async () => {
  // Define the default output file (used when run normally)
  const relativeOutputFile = '.husky/hash.txt';
  const outputFile = path.join(projectDir, relativeOutputFile);

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
  // Extra files to always include
  const extraFiles = [
    path.join(projectDir, 'package.json'),
    path.join(projectDir, 'composer.json'),
    path.join(projectDir, 'requirements.txt'),
    path.join(projectDir, 'requirements-dev.txt')
  ];

  // If invoked as a git merge driver, Git will call this script with three
  // positional arguments: %O (ancestor), %A (current/ours temp file), %B (theirs).
  // In that mode we must write the merged result into the file at %A and
  // exit with code 0 so Git treats the file as resolved. Detect that mode
  // and write to the provided %A path instead of the tracked file.
  if (process.argv.length >= 5) {
    const _ancestorPath = process.argv[2];
    const ourPath = process.argv[3];
    const _theirPath = process.argv[4];

    // Compute the same outputLine as below, then write it to the
    // temp file Git provided as %A.
    const sorted = (await createFileHashes({ projectDir, extensions, excludeDirs, extraFiles })).slice().sort();
    const joined = sorted.join('\n');
    const folderHash = crypto.createHash('sha256').update(joined).digest('hex').slice(0, 16);
    const projectName = path.basename(projectDir);
    const outputLine = `${projectName} a${folderHash}\n`;

    await fs.mkdir(path.dirname(ourPath), { recursive: true }).catch(() => {});
    await fs.writeFile(ourPath, outputLine, 'utf8');
    // Successful merge driver must exit 0 and leave the result in %A.
    console.log(`Merge driver: wrote merged hash to ${ourPath}`);
    process.exit(0);
  }

  // Generate hashes
  const hashArray = await createFileHashes({
    projectDir,
    extensions,
    excludeDirs,
    extraFiles
  });

  // Compute a single checksum for the parent project folder from the sorted file hashes.
  // Join the sorted hash lines with a newline to create a stable input, then hash it.
  const sorted = hashArray.slice().sort();
  const joined = sorted.join('\n');
  const folderHash = crypto.createHash('sha256').update(joined).digest('hex').slice(0, 16);

  // Use the immediate parent folder name as the project key
  const projectName = path.basename(projectDir);
  const outputLine = `${projectName} a${folderHash}\n`;

  // Ensure output directory exists and write the file when run normally
  await fs.mkdir(path.dirname(outputFile), { recursive: true });
  await fs.writeFile(outputFile, outputLine, 'utf-8');

  // Add the hash file to the commit (only for normal runs)
  try {
    execSync(`git add ${relativeOutputFile}`);
  } catch (_err) {
    // ignore git add failures in environments where git isn't available
  }

  console.log(`Parent folder checksum saved to ${relativeOutputFile}`);
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
