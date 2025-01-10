import escapeStringRegexp from 'escape-string-regexp';
import fs from 'fs-extra';
import os from 'os';
import path from 'path';
import * as rimraf from 'rimraf';

/**
 * Deletes files or directories older than the specified number of hours in the given folder.
 * @param {string} folderPath - The folder to scan for old files or directories.
 * @param {number} [hours=24] - The number of hours to check for old files (default is 24 hours).
 * @returns {Promise<void>}
 */
export async function deleteOldFiles(folderPath, hours = 24) {
  try {
    const files = await fs.promises.readdir(folderPath); // List all files and directories in the folder
    const timeLimit = Date.now() - hours * 60 * 60 * 1000; // Convert hours to milliseconds

    for (const file of files) {
      const filePath = path.join(folderPath, file);
      try {
        const stats = await fs.promises.stat(filePath); // Get file or directory stats
        if (stats.mtimeMs < timeLimit) {
          // Compare modified time with the time limit
          console.log(`Deleting: ${filePath}`);
          try {
            await rimraf.rimraf(filePath);
            console.log(`${filePath} deleted successfully`);
          } catch (err) {
            if (err) console.error(`Error deleting ${filePath}:`, err);
          }
        }
      } catch (error) {
        console.error(`Error accessing ${filePath}:`, error);
      }
    }
  } catch (error) {
    console.error(`Error reading directory ${folderPath}:`, error);
  }
}

/**
 * Recursively calculates the total size of a directory or returns the size of a file.
 *
 * @param {string} pathOf - The absolute or relative path to the file or folder.
 * @returns {Promise<number>} - The total size of the directory in bytes, or the file size if the path is a file.
 * @throws {Error} - If the file or directory doesn't exist or an error occurs while reading.
 */
export async function getPathSize(pathOf) {
  const stats = await fs.stat(pathOf);

  // If it's a file, return the file size.
  if (!stats.isDirectory()) {
    return stats.size;
  }

  // If it's a directory, calculate the size of its contents recursively.
  const files = await fs.readdir(pathOf);
  const total = await Promise.all(
    files.map(async (file) => {
      const filePath = path.join(pathOf, file);
      const fileStats = await fs.stat(filePath);

      // Recursively calculate the size if it's a directory.
      if (fileStats.isDirectory()) {
        return getPathSize(filePath);
      }

      // Return the file size if it's a file.
      return fileStats.size;
    })
  );

  // Sum all the sizes.
  return total.reduce((acc, size) => acc + size, 0);
}

const operationQueueremoveStringsFromFile = []; // Queue to hold file operations
let isProcessingremoveStringsFromFile = false; // Flag to check if we are processing an operation

/**
 * Processes the operations in the queue for removing strings from a file.
 * This function ensures that file operations are executed sequentially.
 *
 * @returns {Promise<void>} - A promise that resolves when the operation is complete.
 */
async function processQueueremoveStringsFromFile() {
  if (isProcessingremoveStringsFromFile || operationQueueremoveStringsFromFile.length === 0) {
    return; // If already processing or no operations in queue, exit
  }

  isProcessingremoveStringsFromFile = true; // Set processing flag
  const { filePath, stringsToRemove, debug, resolve } = operationQueueremoveStringsFromFile.shift(); // Get the next operation from the queue

  try {
    // Check if file exists
    if (!fs.existsSync(filePath)) {
      console.warn(`File not found: ${filePath}`);
      return resolve(); // Skip if file not found
    }

    // Read the file content
    const content = await fs.promises.readFile(filePath, 'utf8');

    // Skip if content is empty
    if (!content) {
      console.warn(`File is empty: ${filePath}`);
      return resolve();
    }

    // Create a temporary folder path
    const tempDir = path.join(os.tmpdir(), 'temp-files');
    await fs.promises.mkdir(tempDir, { recursive: true });

    // Copy original file to temp folder
    const tempFilePath = path.join(tempDir, path.basename(filePath));
    await fs.promises.copyFile(filePath, tempFilePath);

    // Normalize stringsToRemove to an array
    const stringsArray = Array.isArray(stringsToRemove) ? stringsToRemove : [stringsToRemove];
    const escaped = stringsArray.map((str) => escapeStringRegexp(str));
    const regex = new RegExp(escaped.join('|'), 'gm');

    // Test regex against content
    if (debug) {
      const matches = content.match(regex);
      console.log(`Matches found: ${matches}`);
    }

    // Modify the content by removing the specified strings
    const modifiedContent = content.replace(regex, '');

    // Save the modified content back to the original file if changes were made
    if (modifiedContent !== content) {
      await fs.promises.writeFile(filePath, modifiedContent, 'utf8');
      if (debug) console.log(`Modified file saved: ${filePath}`);
    } else {
      if (debug) console.log(`No changes made to the file: ${filePath}`);
    }
  } catch (error) {
    if (debug) console.error(`Error removing string from ${filePath}: ${error.message}`);
  } finally {
    isProcessingremoveStringsFromFile = false; // Reset processing flag
    processQueueremoveStringsFromFile(); // Process the next operation in the queue
    resolve(); // Resolve the promise for this operation
  }
}

/**
 * Removes specified strings from a file and saves the modified content back to the original path.
 * This function handles multiple requests to modify the same file and ensures they are processed sequentially.
 *
 * @param {string} filePath - The path to the file from which the strings should be removed.
 * @param {string|string[]} stringsToRemove - A string or an array of strings that should be removed from the file.
 * @param {boolean} [debug=false] - Optional flag to enable debug logging.
 * @returns {Promise<void>} - A promise that resolves when the operation is complete.
 */
export async function removeStringsFromFile(filePath, stringsToRemove, debug = false) {
  return new Promise((resolve) => {
    operationQueueremoveStringsFromFile.push({ filePath, stringsToRemove, debug, resolve });
    processQueueremoveStringsFromFile();
  });
}
