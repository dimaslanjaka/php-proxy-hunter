import { spawnAsync } from 'cross-spawn';
import sftpConfig from '../.vscode/sftp.json' with { type: 'json' };
import { Client } from 'ssh2';
import SftpClient from 'ssh2-sftp-client';

const { host, port, username, password, remotePath } = sftpConfig;

/**
 * Upload a single file to the remote server using ssh2-sftp-client.
 * @param {string} localFile - Local file path to upload.
 * @param {string} remoteFile - Remote file path to upload to.
 * @param {object} [config] - Optional SFTP config override.
 * @returns {Promise<void>}
 */
export async function uploadFile(localFile, remoteFile, config = {}) {
  const sftp = new SftpClient();
  const sftpOptions = {
    host,
    port,
    username,
    password,
    ...config
  };
  try {
    await sftp.connect(sftpOptions);
    console.log(`Uploading file ${localFile} to ${remoteFile} ...`);
    await sftp.put(localFile, remoteFile);
    console.log('File upload complete.');
  } catch (err) {
    console.error('SFTP file upload error:', err);
    throw err;
  } finally {
    sftp.end();
  }
}

/**
 * Recursively upload a local directory to the remote server using ssh2-sftp-client.
 * @param {string} localDir - Local directory to upload.
 * @param {string} remoteDir - Remote directory to upload to.
 * @param {object} [config] - Optional SFTP config override.
 * @returns {Promise<void>}
 */
export async function uploadDir(localDir, remoteDir, config = {}) {
  const sftp = new SftpClient();
  const sftpOptions = {
    host,
    port,
    username,
    password,
    ...config
  };
  try {
    await sftp.connect(sftpOptions);
    console.log(`Uploading ${localDir} to ${remoteDir} ...`);
    await sftp.uploadDir(localDir, remoteDir);
    console.log('Upload complete.');
  } catch (err) {
    console.error('SFTP upload error:', err);
    throw err;
  } finally {
    sftp.end();
  }
}

/**
 * Execute a command on the remote server with .bashrc loaded, streaming output live to the console.
 *
 * @param {import('ssh2').Client} conn - An active ssh2 Client connection.
 * @param {string} command - The shell command to execute remotely.
 * @returns {Promise<{stdout: string, stderr: string, code: number, signal: string}>} Resolves with command output and exit info.
 */
/**
 * Execute a command on the remote server with .bashrc loaded, streaming output live to the console.
 * Always sources nvm from /usr/local/nvm/nvm.sh and sets node version.
 *
 * @param {import('ssh2').Client} conn - An active ssh2 Client connection.
 * @param {string} command - The shell command to execute remotely.
 * @returns {Promise<{stdout: string, stderr: string, code: number, signal: string}>} Resolves with command output and exit info.
 */
export function execWithBashrc(conn, command) {
  // Source .bashrc and nvm for maximum compatibility with interactive shell environments
  const wrapped = `
    source ~/.bashrc >/dev/null 2>&1;
    if [ -f /usr/local/nvm/nvm.sh ]; then
      source /usr/local/nvm/nvm.sh >/dev/null 2>&1;
      nvm use default >/dev/null 2>&1;
    fi;
    corepack enable;
    ${command}
  `;
  return new Promise((resolve, reject) => {
    let stdout = '';
    let stderr = '';
    conn.exec(`bash -lc "${wrapped.replace(/"/g, '\\"')}"`, (err, stream) => {
      if (err) {
        reject(err);
        return;
      }
      stream
        .on('close', (code, signal) => {
          resolve({ stdout, stderr, code, signal });
        })
        .on('data', (data) => {
          stdout += data;
          process.stdout.write(data);
        })
        .stderr.on('data', (data) => {
          stderr += data;
          process.stderr.write(data);
        });
    });
  });
}

/**
 * Connects to the remote server via SSH and performs a `git pull` in the specified remotePath.
 * Streams output live to the console and resolves with the command result.
 *
 * @returns {Promise<{code: number, signal: string, stdout: string, stderr: string}>}
 *   Resolves with the exit code, signal, stdout, and stderr of the git pull command.
 *   Rejects if the SSH connection or git pull fails.
 */
export function gitPull() {
  const conn = new Client();
  return new Promise((resolve, reject) => {
    conn
      .on('ready', async () => {
        console.log('SSH Connection ready');
        let code, signal, error, stdout, stderr;
        try {
          ({ code, signal, stdout, stderr } = await execWithBashrc(conn, `cd ${remotePath} && git pull`));
        } catch (err) {
          error = err;
        }
        conn.end();
        if (error) {
          reject(error);
          return;
        }
        if (code === 0) {
          resolve({ code, signal, stdout, stderr });
        } else {
          reject(new Error(`git pull failed with code ${code}, signal ${signal}`));
        }
      })
      .connect({
        host,
        port,
        username,
        password
      });
  });
}

async function main() {
  const { stdout = undefined } = (await gitPull()) || {};
  if (/up to date/i.test(stdout || '')) {
    // Set maintenance page
    await uploadFile('index.maintenance.html', `${remotePath}/index.html`);
    // Build project
    await spawnAsync('node', ['bin/build-project.mjs'], { stdio: 'inherit', shell: true });
    // Upload built files
    await uploadDir('dist', `${remotePath}/dist`);
    await uploadFile('index.html', `${remotePath}/index.html`);
  }
}

main().catch(console.error);
