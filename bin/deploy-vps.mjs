import { spawnAsync } from 'cross-spawn';
import path from 'path';
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

/** Create new SFTP instance with merged config */
function createSftp(config = {}) {
  const sftp = new SftpClient();
  const opts = { host, port, username, password, ...config };
  return { sftp, opts };
}

/** Safely connect + run + end SFTP */
async function useSftp(config, fn) {
  const { sftp, opts } = createSftp(config);
  try {
    await sftp.connect(opts);
    return await fn(sftp);
  } finally {
    sftp.end();
  }
}

/** Execute SSH with automatic connect/end */
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
 * Upload a single file to the remote server using ssh2-sftp-client.
 */
export async function uploadFile(localFile, remoteFile, config = {}) {
  return useSftp(config, async (sftp) => {
    console.log(`Uploading file ${localFile} → ${remoteFile}`);
    await sftp.put(localFile, remoteFile);
  });
}

/**
 * Recursively upload a directory using ssh2-sftp-client.
 */
export async function uploadDir(localDir, remoteDir, config = {}) {
  return useSftp(config, async (sftp) => {
    console.log(`Uploading ${localDir} → ${remoteDir}`);
    await sftp.uploadDir(localDir, remoteDir);
  });
}

/**
 * Write contents (string or Buffer) to a remote file path via SFTP.
 */
export async function writeRemoteFile(remoteFile, contents, config = {}) {
  const data = Buffer.isBuffer(contents) ? contents : Buffer.from(String(contents));

  return useSftp(config, async (sftp) => {
    console.log(`Writing remote file ${remoteFile}`);

    const parentDir = path.posix.dirname(remoteFile.replace(/\\/g, '/'));

    try {
      await sftp.mkdir(parentDir, true);
    } catch (e) {
      console.debug(`mkdir ${parentDir} skipped:`, e.message || e);
    }

    await sftp.put(data, remoteFile);
  });
}

/**
 * Delete a remote file or directory (recursive if needed).
 */
export async function deleteRemotePath(target, config = {}) {
  return useSftp(config, async (sftp) => {
    console.log(`Deleting remote path ${target}`);

    let existsType = null;
    try {
      existsType = await sftp.exists(target);
    } catch (e) {
      console.debug(`exists() failed:`, e.message || e);
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
  });
}

/* -------------------------------------------------------
 *  SSH EXEC HELPERS
 * ----------------------------------------------------- */

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
        .on('close', (code, signal) => resolve({ stdout, stderr, code, signal }))
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
 * git pull via SSH
 */
export function gitPull() {
  return withSSH(async (conn) => {
    return execWithBashrc(conn, `cd ${remotePath} && git pull`);
  });
}

/**
 * Execute a remote shell command through SSH
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
