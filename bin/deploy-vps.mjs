import { spawnAsync } from 'cross-spawn';
import path from 'path';
import { Client } from 'ssh2';
import SftpClient from 'ssh2-sftp-client';
import { fileURLToPath } from 'url';
import sftpConfig from '../.vscode/sftp.json' with { type: 'json' };

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
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
 * Write contents (string or Buffer) to a remote file path via SFTP.
 * Creates parent directories if they don't exist.
 * @param {string} remoteFile - Remote file path to write to.
 * @param {string|Buffer} contents - The contents to write.
 * @param {object} [config] - Optional SFTP config override.
 * @returns {Promise<void>}
 */
export async function writeRemoteFile(remoteFile, contents, config = {}) {
  const sftp = new SftpClient();
  const sftpOptions = {
    host,
    port,
    username,
    password,
    ...config
  };

  // Normalize contents to Buffer for sftp.put
  const data = Buffer.isBuffer(contents) ? contents : Buffer.from(String(contents), 'utf8');

  try {
    await sftp.connect(sftpOptions);
    console.log(`Writing to remote file ${remoteFile} ...`);

    // Ensure parent directory exists. ssh2-sftp-client doesn't provide a single recursive mkdir in all versions,
    // but its mkdir(path, true) supports recursive creation. We'll attempt it and ignore if not supported.
    const parentDir = path.posix.dirname(remoteFile.replace(/\\/g, '/'));
    try {
      await sftp.mkdir(parentDir, true);
    } catch (e) {
      // If mkdir fails, continue — the put may still succeed if parent exists.
      // Log the error at debug level.
      console.debug(`mkdir for ${parentDir} failed or already exists:`, e.message || e);
    }

    // Upload the buffer directly to the remote path
    await sftp.put(data, remoteFile);
    console.log('Remote file write complete.');
  } catch (err) {
    console.error('SFTP write remote file error:', err);
    throw err;
  } finally {
    sftp.end();
  }
}

/**
 * Delete a remote file or directory. If the path is a directory, attempts recursive removal.
 * @param {string} remotePathToDelete - Remote file or directory path to delete.
 * @param {object} [config] - Optional SFTP config override.
 * @returns {Promise<void>}
 */
export async function deleteRemotePath(remotePathToDelete, config = {}) {
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
    console.log(`Deleting remote path ${remotePathToDelete} ...`);
    // Prefer checking the remote type first to avoid a noisy "delete failed (may be a directory)" message.
    // ssh2-sftp-client offers exists() which returns false | 'd' | '-' | 'l' (dir|file|link) in many versions.
    let existsType = null;
    try {
      existsType = await sftp.exists(remotePathToDelete);
    } catch (e) {
      console.debug('sftp.exists check failed, will attempt delete/rmdir fallbacks:', e.message || e);
    }

    if (existsType === false) {
      console.log('Remote path does not exist, nothing to delete.');
      return;
    }

    if (existsType === 'd') {
      // Path is a directory — try rmdir recursive if supported
      try {
        await sftp.rmdir(remotePathToDelete, true);
        console.log('Remote directory removed.');
        return;
      } catch (dirErr) {
        console.debug(
          'sftp.rmdir failed or not supported, attempting manual recursive delete:',
          dirErr.message || dirErr
        );
      }
    } else if (existsType === '-' || existsType === 'l' || existsType === null) {
      // Treat as a file (or unknown): try delete first, then fall back to rmdir if that fails
      try {
        await sftp.delete(remotePathToDelete);
        console.log('Remote file deleted.');
        return;
      } catch (fileErr) {
        console.debug('sftp.delete failed, attempting rmdir/recursive fallback:', fileErr.message || fileErr);
      }
    }

    // If we reached here, previous attempts failed — attempt manual recursive delete by listing children.
    // This covers servers where rmdir(..., true) isn't supported.
    try {
      const list = await sftp.list(remotePathToDelete);
      for (const item of list) {
        const childPath = `${remotePathToDelete.replace(/\/$/, '')}/${item.name}`;
        if (item.type === 'd') {
          await deleteRemotePath(childPath, config);
        } else {
          await sftp.delete(childPath);
        }
      }
      // After deleting children, remove the directory itself
      await sftp.rmdir(remotePathToDelete);
      console.log('Remote directory removed (manual).');
      return;
    } catch (manualErr) {
      console.error('Failed to delete remote path:', manualErr);
      throw manualErr;
    }
  } catch (err) {
    console.error('SFTP delete remote path error:', err);
    throw err;
  } finally {
    sftp.end();
  }
}

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

/**
 * Execute a command on the remote server via SSH and return the execution result.
 *
 * This helper establishes an ssh2 Client connection to the configured host and
 * uses execWithBashrc to run the provided shell command (which sources ~/.bashrc
 * and prepares nvm/corepack). The promise resolves with the same object that
 * execWithBashrc returns: stdout, stderr, exit code and signal.
 *
 * Note: The function name is prefixed with an underscore to allow it to remain
 * in the source even if not referenced elsewhere (matches allowed unused var pattern /^_/u).
 *
 * @param {string} command - The shell command to execute remotely.
 * @returns {Promise<{stdout: string, stderr: string, code: number|null, signal: string|null}>}
 *    Resolves with stdout, stderr, numeric exit code (or null), and signal (or null).
 *    Rejects on SSH/connect or execution errors.
 */
export async function shell_exec(command, cwd = null) {
  const conn = new Client();
  return new Promise((resolve, reject) => {
    conn
      .on('ready', async () => {
        try {
          if (typeof cwd === 'string' && cwd.length > 0) {
            command = `cd ${cwd} && ${command}`;
          }
          const result = await execWithBashrc(conn, command);
          conn.end();
          resolve(result);
        } catch (err) {
          conn.end();
          reject(err);
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
  // Set maintenance by creating a lightweight lock file so remote processes know a build is in progress
  const lockContents = `build-start:${new Date().toISOString()} pid:${process.pid}\n`;
  await writeRemoteFile(`${remotePath}/tmp/locks/.build-lock`, lockContents);

  // Pull latest changes from git
  await gitPull();

  // Run `python-minimal` installation script on remote server to ensure dependencies are up to date
  console.log('Ensuring python-minimal dependencies are installed on remote server...');
  const { code: pyCode, signal: pySignal } = await shell_exec('bash bin/python-minimal', remotePath);
  if (pyCode !== 0) {
    throw new Error(`Remote python-minimal installation failed with code ${pyCode}, signal ${pySignal}`);
  }

  // Run `composer install --no-dev --no-interaction` when `composer.lock` is not found otherwise `composer update --no-dev --no-interaction`
  console.log('Ensuring PHP dependencies are installed on remote server...');
  const { code: composerCode, signal: composerSignal } = await shell_exec(
    `[ -f ${remotePath}/composer.lock ] && php ${remotePath}/bin/composer.phar install --no-dev --no-interaction || php ${remotePath}/bin/composer.phar update --no-dev --no-interaction`,
    remotePath
  );
  if (composerCode !== 0) {
    throw new Error(`Remote composer installation failed with code ${composerCode}, signal ${composerSignal}`);
  }

  // Build project
  await spawnAsync('node', ['bin/build-project.mjs'], { stdio: 'inherit', shell: true });

  // Clear out old files but preserve certain directories
  const pathsToDelete = [`${remotePath}/dist`, `${remotePath}/index.html`];
  for (const p of pathsToDelete) {
    try {
      await deleteRemotePath(p);
    } catch (err) {
      console.warn(`Warning: Failed to delete ${p}:`, err.message || err);
    }
  }

  // Upload built files
  await uploadDir(path.join(__dirname, '/../dist'), `${remotePath}/dist`);
  await uploadFile(path.join(__dirname, '/../dist/react/index.html'), `${remotePath}/index.html`);

  // Remove the build lock file to signal build completion
  await deleteRemotePath(`${remotePath}/tmp/locks/.build-lock`);
}

if (process.argv.some((arg) => /deploy-vps(\.mjs)?$/u.test(arg))) {
  main()
    .then(() => {
      console.log('Deployment complete.');
      process.exit(0);
    })
    .catch((err) => {
      console.error('Deployment failed:', err);
      process.exit(1);
    });
}
