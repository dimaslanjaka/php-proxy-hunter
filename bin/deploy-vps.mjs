import { spawnAsync } from 'cross-spawn';
import path from 'path';
import fs from 'fs-extra';
import { Client } from 'ssh2';
import SftpClient from 'ssh2-sftp-client';
import { fileURLToPath } from 'url';
import sftpConfig from '../.vscode/sftp.json' with { type: 'json' };

// Resolve dirname
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const { host, port, username, password, remotePath } = sftpConfig;

/* -------------------------------------------------------
 *  SHARED UTILITIES
 * ----------------------------------------------------- */

/**
 * Create a new SFTP client and combined connection options.
 * @param {object} [config={}] - Additional connection options to merge over the default config.
 * @returns {{ sftp: import('ssh2-sftp-client'), opts: object }}
 */
function createSftp(config = {}) {
  const sftp = new SftpClient();
  const opts = { host, port, username, password, ...config };
  return { sftp, opts };
}

/**
 * Safely connect to an SFTP server, execute the provided async callback, and ensure the
 * SFTP connection is closed regardless of success or failure.
 * @template T
 * @param {(sftp: import('ssh2-sftp-client')) => Promise<T>} fn - Function executed with a connected SFTP client.
 * @param {object} [config={}] - Additional connection options passed to `createSftp`.
 * @returns {Promise<T>} The value returned by `fn`.
 */
async function useSftp(fn, config = {}) {
  const { sftp, opts } = createSftp(config);
  try {
    await sftp.connect(opts);
    return await fn(sftp);
  } finally {
    // Ensure client is closed; `end()` is safe to call even if connect failed.
    try {
      await sftp.end();
    } catch (_) {}
  }
}

/**
 * Execute a function with an SSH connection. The connection is opened, the provided
 * async function is executed, and the connection is closed afterwards.
 * @param {(conn: import('ssh2').Client) => Promise<any>} fn - Async function that receives the connected Client.
 * @returns {Promise<any>} Resolves/rejects with the result of `fn`.
 */
function withSSH(fn) {
  const conn = new Client();
  return new Promise((resolve, reject) => {
    conn
      .on('ready', () =>
        fn(conn)
          .then(resolve)
          .catch(reject)
          .finally(() => conn.end())
      )
      .connect({ host, port, username, password });
  });
}

/* -------------------------------------------------------
 *  SFTP OPERATIONS
 * ----------------------------------------------------- */

/**
 * Upload a single local file to the remote server using ssh2-sftp-client.
 * Ensures the SFTP connection is opened and closed via `useSftp`.
 * @param {string} localFile - Local filesystem path to upload.
 * @param {string} remoteFile - Destination path on the remote server (POSIX-style recommended).
 * @param {object} [config={}] - Optional connection overrides passed to `createSftp`.
 * @returns {Promise<void>}
 */
export async function uploadFile(localFile, remoteFile, config = {}) {
  return useSftp(async (sftp) => {
    console.log(`Uploading file ${localFile} → ${remoteFile}`);
    await sftp.put(localFile, remoteFile);
  }, config);
}

/**
 * Recursively upload a directory to the remote server.
 * Uses `ssh2-sftp-client`'s `uploadDir` helper and ensures connection lifecycle via `useSftp`.
 * @param {string} localDir - Local directory path to upload.
 * @param {string} remoteDir - Destination directory on the remote server (POSIX-style recommended).
 * @param {object} [config={}] - Optional connection overrides passed to `createSftp`.
 * @returns {Promise<void>}
 */
export async function uploadDir(localDir, remoteDir, config = {}) {
  return useSftp(async (sftp) => {
    console.log(`Uploading ${localDir} → ${remoteDir}`);
    await sftp.uploadDir(localDir, remoteDir);
  }, config);
}

/**
 * Write contents (string or Buffer) to a remote file path via SFTP.
 * Creates parent directories if necessary.
 * @param {string} remoteFile - Destination path on the remote server.
 * @param {string|Buffer} contents - Data to write to the remote file.
 * @param {object} [config={}] - Optional connection overrides passed to `createSftp`.
 * @returns {Promise<void>}
 */
export async function writeRemoteFile(remoteFile, contents, config = {}) {
  const data = Buffer.isBuffer(contents) ? contents : Buffer.from(String(contents));

  return useSftp(async (sftp) => {
    console.log(`Writing remote file ${remoteFile}`);

    const parentDir = path.posix.dirname(remoteFile.replace(/\\/g, '/'));

    try {
      await sftp.mkdir(parentDir, true);
    } catch (e) {
      const err = e instanceof Error ? e : new Error(String(e));
      console.debug(`mkdir ${parentDir} skipped:`, err.message || err);
    }

    await sftp.put(data, remoteFile);
  }, config);
}

/**
 * Delete a remote file or directory. Performs recursive removal for directories.
 * Attempts efficient removal first, falling back to a manual recursive traversal when necessary.
 * @param {string} target - Remote path to delete.
 * @param {object} [config={}] - Optional connection overrides passed to `createSftp`.
 * @returns {Promise<void>}
 */
export async function deleteRemotePath(target, config = {}) {
  return useSftp(async (sftp) => {
    console.log(`Deleting remote path ${target}`);

    let existsType = null;
    try {
      existsType = await sftp.exists(target);
    } catch (e) {
      const err = e instanceof Error ? e : new Error(String(e));
      console.debug(`exists() failed:`, err.message || err);
    }

    if (!existsType) return;

    if (existsType === 'd') {
      try {
        await sftp.rmdir(target, true);
        return;
      } catch (_) {}
    }

    if (existsType === '-' || existsType === 'l' || existsType === null) {
      try {
        await sftp.delete(target);
        return;
      } catch (_) {}
    }

    // Manual recursive removal fallback
    const list = await sftp.list(target);
    for (const item of list) {
      const child = `${target.replace(/\/$/, '')}/${item.name}`;
      if (item.type === 'd') {
        await deleteRemotePath(child, config);
      } else {
        await sftp.delete(child);
      }
    }
    await sftp.rmdir(target);
  }, config);
}

/* -------------------------------------------------------
 *  SSH EXEC HELPERS
 * ----------------------------------------------------- */

/**
 * Execute a shell command on the remote host wrapped with common environment setup.
 * The command is executed with `bash -lc` so shell features like `&&` and environment
 * sourcing work as expected. Standard output/stderr are captured and streamed locally.
 * @param {import('ssh2').Client} conn - An active SSH2 `Client` instance.
 * @param {string} command - Shell command to run on the remote host.
 * @returns {Promise<{stdout: string, stderr: string, code: number, signal: string}>}
 */
export function execWithBashrc(conn, command) {
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
    let stdout = '',
      stderr = '';

    conn.exec(`bash -lc "${wrapped.replace(/"/g, '\\"')}"`, (err, stream) => {
      if (err) return reject(err);

      stream
        .on('close', (/** @type {any} */ code, /** @type {any} */ signal) => resolve({ stdout, stderr, code, signal }))
        .on('data', (/** @type {string | Uint8Array<ArrayBufferLike>} */ data) => {
          stdout += data;
          process.stdout.write(data);
        })
        .stderr.on('data', (/** @type {string | Uint8Array<ArrayBufferLike>} */ data) => {
          stderr += data;
          process.stderr.write(data);
        });
    });
  });
}

/**
 * Perform `git pull` in the project repository on the remote host via SSH.
 * @returns {Promise<{stdout: string, stderr: string, code: number, signal: string}>}
 */
export function gitPull() {
  return withSSH(async (conn) => {
    return execWithBashrc(conn, `cd ${remotePath} && git pull`);
  });
}

/**
 * Execute a shell command on the remote host via SSH. Optionally run the command from a
 * specific working directory.
 * @param {string} command - Shell command to execute on the remote host.
 * @param {string|null} [cwd=null] - Optional working directory on the remote host.
 * @returns {Promise<{stdout: string, stderr: string, code: number, signal: string}>}
 */
export function shell_exec(command, cwd = null) {
  if (cwd) command = `cd ${cwd} && ${command}`;
  return withSSH((conn) => execWithBashrc(conn, command));
}

/* -------------------------------------------------------
 *  MAIN DEPLOY LOGIC
 * ----------------------------------------------------- */

async function main() {
  const args = process.argv.slice(2);

  const uiOnly = args.includes('--ui') || args.includes('--ui-only');
  const backendOnly = args.includes('--backend') || args.includes('--backend-only');

  /* === Shared lock file contents === */
  const lockContents = `build-start:${new Date().toISOString()} pid:${process.pid}\n`;

  /* -----------------------------
   * UI ONLY DEPLOY
   * --------------------------- */
  if (uiOnly) {
    console.log('UI-only deploy requested. Building...');
    await spawnAsync('yarn', ['build:react'], { stdio: 'inherit', shell: true });

    const deletePaths = [`${remotePath}/dist/react`, `${remotePath}/index.html`];
    for (const p of deletePaths) {
      try {
        await deleteRemotePath(p);
      } catch (_) {}
    }

    await writeRemoteFile(`${remotePath}/tmp/locks/.build-lock`, lockContents);

    await uploadDir(path.join(__dirname, '/../dist/react'), `${remotePath}/dist/react`);
    await uploadFile(path.join(__dirname, '/../dist/react/index.html'), `${remotePath}/index.html`);

    // Upload sitemaps only if they exist
    try {
      const sitemapTxtPath = path.join(__dirname, '/../sitemap.txt');
      const sitemapXmlPath = path.join(__dirname, '/../sitemap.xml');
      if (fs.existsSync(sitemapTxtPath)) {
        await uploadFile(sitemapTxtPath, `${remotePath}/sitemap.txt`);
        console.log('Uploaded sitemap.txt');
      } else {
        console.debug(`Sitemap TXT not found, skipping: ${sitemapTxtPath}`);
      }
      if (fs.existsSync(sitemapXmlPath)) {
        await uploadFile(sitemapXmlPath, `${remotePath}/sitemap.xml`);
        console.log('Uploaded sitemap.xml');
      } else {
        console.debug(`Sitemap XML not found, skipping: ${sitemapXmlPath}`);
      }
    } catch (err) {
      const errMsg = err instanceof Error ? err.message : String(err);
      console.warn('Sitemap upload skipped:', errMsg);
    }

    const { code } = await shell_exec('bash bin/fix-perm', remotePath);
    if (code !== 0) throw new Error('fix-perm failed');

    await deleteRemotePath(`${remotePath}/tmp/locks/.build-lock`);
    console.log('UI-only deploy complete.');
    return;
  }

  /* -----------------------------
   * BACKEND ONLY DEPLOY
   * --------------------------- */
  if (backendOnly) {
    await writeRemoteFile(`${remotePath}/tmp/locks/.build-lock`, lockContents);

    await gitPull();

    const { code } = await shell_exec('bash bin/fix-perm', remotePath);
    if (code !== 0) throw new Error('fix-perm failed');

    await deleteRemotePath(`${remotePath}/tmp/locks/.build-lock`);
    console.log('Backend-only deploy complete.');
    return;
  }

  /* -----------------------------
   * FULL DEPLOY
   * --------------------------- */

  await writeRemoteFile(`${remotePath}/tmp/locks/.build-lock`, lockContents);

  await gitPull();

  console.log('Ensuring python-minimal...');
  const { code: pyCode } = await shell_exec('bash bin/python-minimal', remotePath);
  if (pyCode !== 0) throw new Error('python-minimal failed');

  console.log('Ensuring PHP dependencies...');
  const { code: composerCode } = await shell_exec(
    `export COMPOSER_ALLOW_SUPERUSER=1; if [ -f ${remotePath}/composer.lock ];
     then php ${remotePath}/bin/composer.phar install --no-dev --no-interaction;
     else php ${remotePath}/bin/composer.phar update --no-dev --no-interaction; fi;`,
    remotePath
  );
  if (composerCode !== 0) throw new Error('composer install/update failed');

  console.log('Building project...');
  const forwarded = process.argv.slice(2);
  await spawnAsync('node', ['bin/build-project.mjs', ...forwarded], {
    stdio: 'inherit',
    shell: true
  });

  const removeOld = [`${remotePath}/dist`, `${remotePath}/index.html`];
  for (const p of removeOld) {
    try {
      await deleteRemotePath(p);
    } catch (_) {}
  }

  await uploadDir(path.join(__dirname, '/../dist'), `${remotePath}/dist`);
  await uploadFile(path.join(__dirname, '/../dist/react/index.html'), `${remotePath}/index.html`);

  // Upload sitemaps only if they exist
  try {
    const sitemapTxtPath = path.join(__dirname, '/../.deploy_git/sitemap.txt');
    const sitemapXmlPath = path.join(__dirname, '/../.deploy_git/sitemap.xml');
    if (fs.existsSync(sitemapTxtPath)) {
      await uploadFile(sitemapTxtPath, `${remotePath}/sitemap.txt`);
      console.log('Uploaded sitemap.txt');
    } else {
      console.debug(`Sitemap TXT not found, skipping: ${sitemapTxtPath}`);
    }
    if (fs.existsSync(sitemapXmlPath)) {
      await uploadFile(sitemapXmlPath, `${remotePath}/sitemap.xml`);
      console.log('Uploaded sitemap.xml');
    } else {
      console.debug(`Sitemap XML not found, skipping: ${sitemapXmlPath}`);
    }
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    console.warn('Sitemap upload skipped:', errMsg);
  }

  const { code: permCode } = await shell_exec('bash bin/fix-perm', remotePath);
  if (permCode !== 0) throw new Error('fix-perm failed');

  await deleteRemotePath(`${remotePath}/tmp/locks/.build-lock`);

  console.log('Restoring local vite...');
  await spawnAsync('yarn', ['prepare:react'], { stdio: 'inherit', shell: true });
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
