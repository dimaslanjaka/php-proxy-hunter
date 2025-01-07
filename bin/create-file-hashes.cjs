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

  // Find files with the specified extensions, compute their hash, and save to hashArray
  await Promise.all(
    extensions.map(async (ext) => {
      const files = glob.sync(`**/*.${ext}`, {
        cwd: projectDir,
        ignore: ignorePatterns,
        absolute: true
      });

      await Promise.all(
        files.map(async (file) => {
          try {
            const stats = await fs.stat(file);
            const pseudoHash = `${stats.size}-${stats.mtimeMs}`;
            const hash = crypto.createHash('sha256').update(pseudoHash).digest('hex');
            const relativePath = path.relative(projectDir, file);
            hashArray.push(`${relativePath} ${hash}`);
          } catch (err) {
            console.error(`Error processing file: ${file}`, err);
          }
        })
      );
    })
  );

  // Sort the hashArray by file paths
  hashArray.sort((a, b) => a.localeCompare(b));

  // Output the sorted hashes to the file
  await fs.writeFile(outputFile, hashArray.join('\n'), 'utf-8');

  // Add the hash file to the commit
  execSync(`git add ${relativeOutputFile}`);

  console.log(`Hashes saved to ${relativeOutputFile}`);
})();
