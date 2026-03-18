/**
 * Auto-download and extract the latest SQLite precompiled binary
 * for the current OS/arch.
 */

import https from 'https';
import os from 'os';
import path from 'upath';
import fs from 'fs-extra';
import { execSync } from 'child_process';

// === Fetch download page and extract CSV ===
/**
 * Fetches SQLite download metadata and returns the embedded CSV rows.
 *
 * @returns {Promise<string[]>} CSV lines extracted from the download page.
 */
async function fetchDownloadCSV() {
  return new Promise((resolve, reject) => {
    https
      .get('https://www.sqlite.org/download.html', (res) => {
        let data = '';
        res.on('data', (chunk) => (data += chunk));
        res.on('end', () => {
          const match = data.match(/<!--\s*Download product data([\s\S]*?)-->/);
          if (!match) return reject(new Error('Download CSV not found'));
          const csv = match[1].trim().split('\n');
          resolve(csv);
        });
      })
      .on('error', reject);
  });
}

/**
 * Selects the best matching SQLite tools binary for the current OS and architecture.
 *
 * @param {string[]} csvLines - Array of CSV lines from the SQLite download page.
 * @returns {{ relative: string, filename: string }|undefined}
 *   An object with the relative download path and filename, or undefined if not found.
 */
function pickDownload(csvLines) {
  const platform = os.platform();
  const arch = os.arch();

  let platformTokens;
  switch (platform) {
    case 'win32':
      platformTokens = ['win'];
      break;
    case 'darwin':
      platformTokens = ['osx', 'mac'];
      break;
    case 'linux':
      platformTokens = ['linux'];
      break;
    default:
      console.error(`Unsupported platform: ${platform} ${arch}`);
      return undefined;
  }

  let archTokens;
  switch (arch) {
    case 'x64':
      archTokens = ['x64', 'x86_64', 'amd64'];
      break;
    case 'ia32':
      archTokens = ['x86', 'i386'];
      break;
    case 'arm64':
      archTokens = ['arm64', 'aarch64'];
      break;
    case 'arm':
      archTokens = ['armv7', 'arm'];
      break;
    default:
      archTokens = [arch.toLowerCase()];
      break;
  }

  const toolPaths = csvLines
    .map((line) => line.split(','))
    .map((parts) => parts[2]?.trim().replace(/^"|"$/g, ''))
    .filter(Boolean)
    .filter((relative) => path.basename(relative).toLowerCase().startsWith('sqlite-tools-'));

  const rankedTools = toolPaths
    .map((relative) => {
      const name = path.basename(relative).toLowerCase();
      const platformScore = platformTokens.some((token) => name.includes(token)) ? 2 : 0;
      const archScore = archTokens.some((token) => name.includes(token)) ? 1 : 0;
      return { relative, platformScore, archScore, score: platformScore + archScore };
    })
    .filter((entry) => entry.platformScore > 0)
    .sort((a, b) => b.score - a.score);

  const selected = rankedTools[0];
  if (!selected) {
    console.error(`No sqlite-tools found for ${platform}-${arch}`);
    return undefined;
  }

  if (selected.archScore === 0) {
    console.warn(`No exact arch match for ${platform}-${arch}; using ${path.basename(selected.relative)}`);
  }

  return {
    relative: selected.relative,
    filename: path.basename(selected.relative)
  };
}

// === Download helper ===
/**
 * Downloads a remote file to a local destination path.
 *
 * @param {string} url - Absolute URL of the file to download.
 * @param {string} dest - Absolute or relative destination file path.
 * @returns {Promise<void>} Resolves after the file is fully written.
 */
async function downloadFile(url, dest) {
  return new Promise((resolve, reject) => {
    const file = fs.createWriteStream(dest);
    https
      .get(url, (res) => {
        if (res.statusCode !== 200) {
          reject(new Error(`Download failed: ${res.statusCode}`));
          return;
        }
        res.pipe(file);
        file.on('finish', () => file.close(() => resolve()));
      })
      .on('error', reject);
  });
}

// === Check if download is needed ===
/**
 * Checks whether the remote file should be downloaded by comparing file sizes.
 *
 * @param {string} url - Absolute URL of the remote file.
 * @param {string} local - Local file path to compare against.
 * @returns {Promise<boolean>} True when download is needed, otherwise false.
 */
async function shouldDownload(url, local) {
  if (!fs.existsSync(local)) return true;
  const localSize = fs.statSync(local).size;
  return new Promise((resolve, reject) => {
    https
      .get(url, { method: 'HEAD' }, (res) => {
        const remoteSize = parseInt(res.headers['content-length'] || '0', 10);
        if (!remoteSize || isNaN(remoteSize)) {
          return resolve(true);
        }
        resolve(localSize !== remoteSize);
      })
      .on('error', reject);
  });
}

// === Main ===
(async () => {
  try {
    console.log('Fetching SQLite download list...');
    const csv = await fetchDownloadCSV();
    const { relative = undefined, filename = undefined } = pickDownload(csv) || {};

    if (!relative || !filename) return console.error('No suitable SQLite binary found');

    const base = 'https://www.sqlite.org';
    const url = `${base}/${relative}`;
    console.log('Resolved URL:', url);

    const ext = path.extname(filename);
    // Save download to process.cwd()/tmp/download
    const tmpDir = path.resolve(process.cwd(), 'tmp', 'download');
    if (!fs.existsSync(tmpDir)) {
      fs.mkdirSync(tmpDir, { recursive: true });
    }
    const local = path.join(tmpDir, filename);

    // Set extraction directory to /bin in process.cwd()
    const binDir = path.resolve(process.cwd(), 'bin');
    if (!fs.existsSync(binDir)) {
      fs.mkdirSync(binDir, { recursive: true });
    }

    if (await shouldDownload(url, local)) {
      console.log('Downloading:', filename);
      await downloadFile(url, local);
      console.log('Download complete:', local);
    } else {
      console.log('Local file is up to date, skipping download.');
    }

    console.log('Extracting...');
    if (ext === '.zip') {
      // Use unzipper for progress
      const unzipper = await import('unzipper');
      const directory = await unzipper.Open.file(local);
      const total = directory.files.filter((f) => f.type !== 'Directory').length;
      let count = 0;
      await new Promise((resolve, reject) => {
        fs.createReadStream(local)
          .pipe(unzipper.Parse())
          .on('entry', function (entry) {
            let relativePath;
            const parts = entry.path.split(/[/\\]/);
            if (parts.length > 1) {
              relativePath = parts.slice(1).join(path.sep);
            } else {
              relativePath = entry.path;
            }
            if (!relativePath) {
              entry.autodrain();
              return;
            }
            const filePath = path.join(binDir, relativePath);
            if (entry.type === 'Directory') {
              fs.mkdirSync(filePath, { recursive: true });
              entry.autodrain();
            } else {
              count++;
              const percent = ((count / total) * 100).toFixed(1);
              process.stdout.write(`\r${percent}% (${count}/${total})` + ' '.repeat(40));
              const dir = path.dirname(filePath);
              fs.mkdirSync(dir, { recursive: true });
              entry.pipe(fs.createWriteStream(filePath));
            }
          })
          .on('close', () => {
            process.stdout.write('\nExtraction complete. Total files: ' + count + '\n');
            resolve(undefined);
          })
          .on('error', reject);
      });
    } else if (ext === '.gz') {
      execSync(`mkdir -p '${binDir}' && tar -xzf '${local}' -C '${binDir}' --strip-components=1`, { stdio: 'inherit' });
    }

    // Auto-create bin/sqlite3.cmd for Windows CLI usage, only if sqlite3.exe exists
    const exePath = path.join(binDir, 'sqlite3.exe');
    if (fs.existsSync(exePath)) {
      const cmdScript = `@echo off\r\nREM Forward all arguments to sqlite3.exe in the same directory\r\nset SCRIPT_DIR=%~dp0\r\n"%SCRIPT_DIR%sqlite3.exe" %*\r\n`;
      const cmdPath = path.join(binDir, 'sqlite3.cmd');
      await fs.writeFile(cmdPath, cmdScript, 'utf8');
    }

    console.log('✅ SQLite installed in ./bin/');
    console.log('Run ./bin/sqlite3[.exe] --version to verify.');
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    console.error('❌ Error:', message);
  }
})();
