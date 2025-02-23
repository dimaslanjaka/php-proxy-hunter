import crypto from 'crypto';
import fs from 'fs-extra';
import os from 'os';
import { dirname, join, normalize } from 'path';
import path from 'upath';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Set Timezone
process.env.TZ = 'Asia/Jakarta';

// Determine if the application is running as a script or a compiled executable
let __CWD__ = path.join(__dirname, '../');
if (process.pkg) {
  __CWD__ = dirname(process.execPath); // For a compiled executable
} else {
  __CWD__ = process.cwd(); // When running as a script
}

/**
 * Check if the script is compiled with a tool like `pkg`.
 *
 * @returns {boolean} True if the script is compiled with a tool like pkg or similar.
 */
function isNuitka() {
  const isNuitka = process.pkg !== undefined;
  const isNuitkaOneFile = process.env.NUITKA_ONEFILE_PARENT !== undefined;
  return isNuitka || isNuitkaOneFile;
}

export const isNuitkaStandalone = process.pkg !== undefined;
export const isNuitkaOneFile = process.env.NUITKA_ONEFILE_PARENT !== undefined;

/**
 * Get the path for a file within a compiled application.
 *
 * @param {string} filePath - The relative file path.
 * @returns {string} The absolute file path.
 */
export function getNuitkaFile(filePath) {
  // Get the directory of the current script
  const __filename = fileURLToPath(import.meta.url);
  const scriptDir = dirname(__filename);

  // Go up one directory level to the root of the project
  const rootDir = dirname(scriptDir);

  return join(rootDir, filePath);
}

/**
 * Get the relative path from the current working directory (CWD).
 *
 * @param {...string} args - Variable number of path components.
 * @returns {string} The normalized relative path.
 */
export function getRelativePath(...args) {
  const joinPath = join(...args);
  let result = normalize(join(__CWD__, joinPath));

  if (isNuitka()) {
    result = normalize(join(dirname(process.argv[0]), joinPath));
  }

  return result;
}

/**
 * Ensures that the directory exists, creating it recursively if needed.
 * @param {string} dirPath - The path of the directory to ensure.
 * @returns {Promise<void>} A promise that resolves when the directory is created or already exists.
 */
export async function ensureDirectory(dirPath) {
  return new Promise((resolve, reject) => {
    fs.mkdir(dirPath, { recursive: true }, (err) => {
      if (err) {
        console.error(`Failed to create directory ${dirPath}:`, err);
        reject(err);
      } else {
        resolve();
      }
    });
  });
}

/**
 * Reads the content of a file.
 * @param {string} filePath - The path of the file to read.
 * @returns {Promise<string>} A promise that resolves with the file content.
 */
export async function readFile(filePath) {
  return new Promise((resolve, reject) => {
    fs.readFile(filePath, 'utf8', (err, data) => {
      if (err) {
        console.error(`Failed to read file ${filePath}:`, err);
        reject(err);
      } else {
        resolve(data);
      }
    });
  });
}

/**
 * Writes content to a file, creating the directory if it does not exist.
 * @param {string} filePath - The path of the file to write.
 * @param {string} content - The content to write to the file.
 * @returns {Promise<void>} A promise that resolves when the file is written.
 */
export async function writeFile(filePath, content) {
  const dir = path.dirname(filePath);
  try {
    await ensureDirectory(dir);
    return new Promise((resolve, reject) => {
      fs.writeFile(filePath, content, 'utf8', (err) => {
        if (err) {
          console.error(`Failed to write file ${filePath}:`, err);
          reject(err);
        } else {
          resolve();
        }
      });
    });
  } catch (err) {
    console.error(`Failed to ensure directory for file ${filePath}:`, err);
  }
}

/**
 * Generates an MD5 hash of the given input.
 * @param {string | Buffer} data - The input data to hash. Can be a string or Buffer.
 * @returns {string} The MD5 hash of the input data as a hexadecimal string.
 */
export function md5(data) {
  return crypto.createHash('md5').update(data).digest('hex');
}

/**
 * Determines whether the application is in debug mode.
 *
 * Debug mode is activated based on several conditions:
 * - If the code is running in a GitHub CI environment or GitHub Codespaces.
 * - If the hostname of the machine matches one of the debug devices specified
 *   in the `DEBUG_DEVICES` environment variable.
 * - If the hostname starts with 'codespaces-'.
 *
 * @returns {boolean} True if in debug mode, false otherwise.
 */
export function isDebug() {
  // Check if running in a GitHub CI environment
  const isGitHubCI = process.env.CI === 'true' && process.env.GITHUB_ACTIONS === 'true';

  // Check if running in GitHub Codespaces
  const isGitHubCodespaces = process.env.CODESPACES === 'true';

  // Debug mode is active in GitHub CI or GitHub Codespaces environments
  if (isGitHubCI || isGitHubCodespaces) {
    return true;
  }

  // Get the list of debug devices from the environment variable
  const envPath = path.resolve(process.cwd(), '.env');
  let debugDevices = [];

  if (fs.existsSync(envPath)) {
    const envContent = fs.readFileSync(envPath, 'utf8');
    const match = envContent.match(/^DEBUG_DEVICES=(.+)$/m);
    if (match && match[1]) {
      debugDevices = match[1].split(',').map((device) => device.trim());
    }
  }

  // Get the hostname of the current machine
  const hostname = os.hostname();

  // Debug mode is active if the hostname starts with 'codespaces-'
  if (hostname.startsWith('codespaces-')) {
    return true;
  }

  // Debug mode is active if the hostname is in the list of debug devices
  return debugDevices.includes(hostname);
}
