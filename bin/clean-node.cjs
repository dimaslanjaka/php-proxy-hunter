const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

/**
 * Spawns a child process asynchronously and returns a Promise that resolves when the process exits with code 0,
 * or rejects if the process exits with a non-zero code or encounters an error.
 *
 * @param {string} command - The command to run.
 * @param {string[]} args - List of string arguments.
 * @param {import('child_process').SpawnOptions} [options={}] - Options to pass to the spawn function.
 * @returns {Promise<void>} A Promise that resolves when the process exits successfully, or rejects on error or non-zero exit code.
 */
function spawnAsync(command, args, options = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, options);
    child.on('close', (code) => {
      if (code === 0) {
        resolve();
      } else {
        reject(new Error(`Process exited with code ${code}`));
      }
    });
    child.on('error', reject);
  });
}

/**
 * Generates an array of glob patterns for directories within the specified path,
 * including patterns for directories starting with a-z, @a-z, @, and all others.
 *
 * @param {string} dirPath - The base directory path to generate patterns for.
 * @returns {string[]} An array of glob patterns for matching directories.
 */
function getAZDirectories(dirPath) {
  const directories = [`${dirPath}/.*`];

  for (let i = 0; i < 26; i++) {
    const letter = String.fromCharCode(65 + i).toLowerCase(); // Generates letters a-z
    directories.push(`${dirPath}/${letter}*`);
    directories.push(`${dirPath}/@${letter}*`);
  }

  directories.push(`${dirPath}/@*`);
  directories.push(`${dirPath}/*`);

  return directories.map((str) => str.replace(/\\/g, '/'));
}

const nodeModulesPath = path.resolve(process.cwd(), 'node_modules');
if (fs.existsSync(nodeModulesPath)) {
  const dirs = getAZDirectories('node_modules');
  (async () => {
    for (const pattern of dirs) {
      console.log(`Cleaning: ${pattern}`);
      // Use npx rimraf to remove directories matching the pattern
      // The '--yes' flag is used to skip confirmation prompts
      await spawnAsync('npx', ['rimraf', pattern, '--yes'], { stdio: 'inherit' });
    }
    console.log(`Cleaning ${nodeModulesPath}`);
    await spawnAsync('npx', ['rimraf', nodeModulesPath, '--yes'], { stdio: 'inherit' });
  })();
}
