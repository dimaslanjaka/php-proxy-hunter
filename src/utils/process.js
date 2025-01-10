import { exec } from 'child_process';
/**
 * Checks if a process is running on Windows.
 * @param {string} processName - The name of the process to check for.
 * @returns {Promise<boolean>} - A promise that resolves to true if the process is running, false otherwise.
 */
export function isProcessRunning(processName) {
  return new Promise((resolve, reject) => {
    exec('tasklist', (error, stdout) => {
      if (error) {
        return reject(error);
      }

      // Split the output into lines
      const processes = stdout.split('\n');
      // Check if any line contains the process name exactly
      const isRunning = processes.some((line) => line.trim().startsWith(processName));
      resolve(isRunning);
    });
  });
}
