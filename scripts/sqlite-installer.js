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

          if (!match) {
            return reject(new Error('Download CSV not found'));
          }

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

  let target;

  if (platform === 'win32') {
    target = arch === 'x64' ? 'win-x64' : 'win-x86';
  } else if (platform === 'darwin') {
    target = arch === 'arm64' ? 'osx-arm64' : 'osx-x86';
  } else if (platform === 'linux') {
    target = arch === 'arm64' ? 'linux-aarch64' : 'linux-x86_64';
  } else {
    throw new Error(`Unsupported platform: ${platform} ${arch}`);
  }

  const tool = csvLines.map((line) => line.split(',')).find((fields) => fields[2]?.includes(`sqlite-tools-${target}`));

  if (!tool) {
    throw new Error(`No sqlite-tools found for ${target}`);
  }

  return {
    relative: tool[2],
    filename: path.basename(tool[2])
  };
}

// === Download helper ===
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

        file.on('finish', () => file.close(resolve));
      })
      .on('error', reject);
  });
}

// === Check if download is needed ===
async function shouldDownload(url, local) {
  if (!fs.existsSync(local)) {
    return true;
  }

  const localSize = fs.statSync(local).size;

  return new Promise((resolve, reject) => {
    const req = https.request(
      url,
      {
        method: 'HEAD'
      },
      (res) => {
        const remoteSize = parseInt(res.headers['content-length'], 10);

        if (!remoteSize || Number.isNaN(remoteSize)) {
          return resolve(true);
        }

        resolve(localSize !== remoteSize);
      }
    );

    req.on('error', reject);
    req.end();
  });
}

// === Main ===
(async () => {
  try {
    console.log('Fetching SQLite download list...');

    const csv = await fetchDownloadCSV();
    const { relative, filename } = pickDownload(csv);

    const base = 'https://www.sqlite.org';
    const url = `${base}/${relative}`;

    console.log('Resolved URL:', url);

    const ext = path.extname(filename);

    // Save download to process.cwd()/tmp/download
    const tmpDir = path.resolve(process.cwd(), 'tmp', 'download');
    await fs.ensureDir(tmpDir);

    const local = path.join(tmpDir, filename);

    // Set extraction directory to /bin in process.cwd()
    const binDir = path.resolve(process.cwd(), 'bin');
    await fs.ensureDir(binDir);

    let downloaded = false;

    if (await shouldDownload(url, local)) {
      console.log('Downloading:', filename);

      await downloadFile(url, local);

      console.log('Download complete:', local);

      downloaded = true;
    } else {
      console.log('Local file is up to date, skipping download.');
    }

    const sqliteBinary = os.platform() === 'win32' ? path.join(binDir, 'sqlite3.exe') : path.join(binDir, 'sqlite3');

    const needExtract = downloaded || !fs.existsSync(sqliteBinary);

    if (needExtract) {
      console.log('Extracting...');

      if (ext === '.zip') {
        if (os.platform() === 'win32') {
          execSync(
            `powershell -NoProfile -NonInteractive -Command "Expand-Archive -Path '${local}' -DestinationPath '${binDir}' -Force"`,
            {
              stdio: 'inherit'
            }
          );
        } else {
          execSync(`unzip -o '${local}' -d '${binDir}'`, {
            stdio: 'inherit'
          });
        }
      } else if (ext === '.gz') {
        execSync(`mkdir -p '${binDir}' && tar -xzf '${local}' -C '${binDir}' --strip-components=1`, {
          stdio: 'inherit'
        });
      }

      // Auto-create bin/sqlite3.cmd for Windows CLI usage, only if sqlite3.exe exists
      const exePath = path.join(binDir, 'sqlite3.exe');

      if (fs.existsSync(exePath)) {
        const cmdScript = `@echo off\r
REM Forward all arguments to sqlite3.exe in the same directory\r
set SCRIPT_DIR=%~dp0\r
"%SCRIPT_DIR%sqlite3.exe" %*\r
`;

        const cmdPath = path.join(binDir, 'sqlite3.cmd');

        await fs.writeFile(cmdPath, cmdScript, 'utf8');
      }
    } else {
      console.log('SQLite binary already exists, skipping extraction.');
    }

    console.log('✅ SQLite installed in ./bin/');
    console.log('Run ./bin/sqlite3[.exe] --version to verify.');
  } catch (err) {
    console.error('❌ Error:', err.message);
  }
})();
