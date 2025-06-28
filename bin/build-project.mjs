import * as cp from 'cross-spawn'; // For running command-line processes
import * as glob from 'glob'; // For matching files using glob patterns
import path from 'node:path'; // For path operations
import { fileURLToPath } from 'node:url';

// Define the current working directory (base path)
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const cwd = path.join(__dirname, '../');

// Glob pattern to find all rollup config files in the project
const rollupConfigs = glob.sync('rollup.*.{js,cjs,mjs}', { cwd, absolute: true });

// Loop over each rollup config file and build it
rollupConfigs.forEach((f) => {
  const filename = path.basename(f); // Extract the file name from the full path
  console.log('building', filename); // Log the build process for each config file
  cp.spawnSync('rollup', ['-c', f], { stdio: 'inherit', cwd }); // Run rollup with the config file
});
