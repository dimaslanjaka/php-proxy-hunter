// Import necessary modules
const cp = require('cross-spawn'); // For running command-line processes
const path = require('path'); // For path operations
const glob = require('glob'); // For matching files using glob patterns
const fs = require('fs'); // For file system operations
const { dotenvConfig } = require('../.env.cjs'); // Import environment variables

// Define the current working directory (base path)
const cwd = path.join(__dirname, '../');

// Glob pattern to find all rollup config files in the project
const rollupConfigs = glob.sync('rollup.*.{js,cjs}', { cwd, absolute: true });

// Create the environment configuration object
const envConfig = {
  GITHUB_TOKEN_READ_ONLY: dotenvConfig.GITHUB_TOKEN_READ_ONLY, // Add GitHub token
  isDebug: dotenvConfig.isDebug, // Add debug flag
  PROJECT_DIR: cwd // Add project directory path
};

// Write environment config to a JSON file
fs.writeFileSync(path.join(cwd, '.env.build.json'), JSON.stringify(envConfig, null, 2));

// Loop over each rollup config file and build it
rollupConfigs.forEach((f) => {
  const filename = path.basename(f); // Extract the file name from the full path
  console.log('building', filename); // Log the build process for each config file
  cp.spawnSync('rollup', ['-c', f], { stdio: 'inherit', cwd }); // Run rollup with the config file
});
